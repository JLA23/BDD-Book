import java.sql.*;
import java.util.*;
import java.util.stream.Collectors;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.io.*;
import java.util.Base64;
import javax.mail.*;
import javax.mail.internet.*;
import net.ucanaccess.jdbc.UcanaccessDriver;

/**
 * RecoverBDD-Books V3 - Version améliorée avec système de queue
 * 
 * Améliorations :
 * - Système de queue pour traitement asynchrone
 * - Calcul de hash MD5 pour détection rapide des changements
 * - Détection des transferts de livres entre utilisateurs
 * - Gestion des erreurs avec retry
 * - Logging détaillé et traçabilité complète
 */
public class Main {
    final static Calendar calendar = Calendar.getInstance();
    final static java.util.Date currentTime = calendar.getTime();
    
    // Statistiques
    static int insertCount = 0;
    static int updateCount = 0;
    static int deleteCount = 0;
    static int transferCount = 0;
    static int queuedCount = 0;

    public static void sendEmail(Properties prop, String error) throws UnsupportedEncodingException {
        String userEmail = prop.getProperty("EMAIL_USER");
        String password = prop.getProperty("EMAIL_PASSWORD");
        String sender = prop.getProperty("EMAIL");
        String toEmail = prop.getProperty("EMAIL_TO");

        String content = "Bonjour\nUne erreur s'est produite lors de l'exécution du script JAVA RecoverBDD-Books V3.\nVoici l'erreur :\n" + error;

        Properties mailProps = new Properties();
        mailProps.put("mail.smtp.host", prop.getProperty("SMTP_MAIL"));
        mailProps.put("mail.smtp.port", prop.getProperty("PORT_SMPT"));
        mailProps.put("mail.smtp.auth", true);
        mailProps.put("mail.smtp.starttls.enable", true);
        mailProps.put("mail.smtp.ssl.protocols", prop.getProperty("PROTOCAL_SSL"));

        Session session = Session.getInstance(mailProps, new Authenticator() {
            protected PasswordAuthentication getPasswordAuthentication() {
                return new PasswordAuthentication(userEmail, password);
            }
        });

        try {
            Message message = new MimeMessage(session);
            message.setRecipient(Message.RecipientType.TO, new InternetAddress(toEmail));
            message.setFrom(new InternetAddress(sender, "RecoverBDD-Books V3"));
            message.setSubject("Erreur RecoverBDD-Books V3");
            message.setText(content);
            message.setSentDate(new java.util.Date());
            Transport.send(message);
        } catch (MessagingException e) {
            System.out.println("Email non envoyé");
            e.printStackTrace();
        }
    }

    public static Connection getDBData(Properties prop) throws SQLException, ClassNotFoundException {
        Class.forName("net.ucanaccess.jdbc.UcanaccessDriver");
        return DriverManager.getConnection("jdbc:ucanaccess://" + prop.getProperty("PATH") + prop.getProperty("DB_ACCESS"));
    }

    public static Connection getDBMysql(Properties prop, String database) throws SQLException, ClassNotFoundException {
        Class.forName("com.mysql.cj.jdbc.Driver");
        return DriverManager.getConnection("jdbc:mysql://" + prop.getProperty("DB_MYSQL_SERVER") + ":" + prop.getProperty("DB_MYSQL_PORT") + "/" + database + "?useSSL=false&allowPublicKeyRetrieval=true",
                prop.getProperty("DB_MYSQL_USER"), prop.getProperty("DB_MYSQL_PASSWORD"));
    }

    public static Connection getDBMaria(Properties prop, String database) throws SQLException, ClassNotFoundException {
        Class.forName("org.mariadb.jdbc.Driver");
        return DriverManager.getConnection("jdbc:mariadb://" + prop.getProperty("DB_MYSQL_SERVER") + ":" + prop.getProperty("DB_MYSQL_PORT") + "/" + database + "?allowPublicKeyRetrieval=true&useSSL=false",
                prop.getProperty("DB_MYSQL_USER"), prop.getProperty("DB_MYSQL_PASSWORD"));
    }

    public static void main(String[] args) throws SQLException, ClassNotFoundException, IOException {
        String configFilePath = "config/config.properties";
        FileInputStream propsInput = new FileInputStream(configFilePath);
        Properties prop = new Properties();
        prop.load(propsInput);

        System.out.println("=== RecoverBDD-Books V3 Direct ===");
        System.out.println("Démarrage : " + currentTime);

        try {
            // Phase 1: Copier Access vers MySQL temp
            recoverBDD(prop);
            
            // Phase 2: Comparer et appliquer directement dans DatabaseBookRef
            verifyDataDirect(prop);
            
            printStats();
        } catch (Exception e) {
            sendEmail(prop, e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Calcule le hash MD5 d'une ligne pour détecter les changements
     */
    private static String computeRowHash(Map<String, Object> row) throws NoSuchAlgorithmException {
        StringBuilder data = new StringBuilder();
        
        // Trier les clés pour avoir un hash cohérent
        List<String> keys = new ArrayList<>(row.keySet());
        Collections.sort(keys);
        
        for (String key : keys) {
            if ("TRAITE".equals(key) || "DATELASTTRAITE".equals(key)) continue;
            
            Object value = row.get(key);
            if (value != null) {
                if (value instanceof byte[]) {
                    data.append(key).append(":").append(Arrays.toString((byte[])value)).append(";");
                } else {
                    data.append(key).append(":").append(value.toString()).append(";");
                }
            }
        }
        
        MessageDigest md = MessageDigest.getInstance("MD5");
        byte[] hashBytes = md.digest(data.toString().getBytes());
        
        StringBuilder hexString = new StringBuilder();
        for (byte b : hashBytes) {
            String hex = Integer.toHexString(0xff & b);
            if (hex.length() == 1) hexString.append('0');
            hexString.append(hex);
        }
        return hexString.toString();
    }

    /**
     * Convertit une Map en JSON simple (sans dépendance externe)
     */
    private static String mapToJson(Map<String, Object> map) {
        StringBuilder json = new StringBuilder("{");
        boolean first = true;
        
        for (Map.Entry<String, Object> entry : map.entrySet()) {
            if (!first) json.append(",");
            first = false;
            
            json.append("\"").append(escapeJson(entry.getKey())).append("\":");
            
            Object value = entry.getValue();
            if (value == null) {
                json.append("null");
            } else if (value instanceof String) {
                json.append("\"").append(escapeJson(value.toString())).append("\"");
            } else if (value instanceof Number || value instanceof Boolean) {
                json.append(value.toString());
            } else if (value instanceof byte[]) {
                // Encoder les données binaires (images) en base64
                String base64 = Base64.getEncoder().encodeToString((byte[]) value);
                json.append("\"").append(base64).append("\"");
            } else if (value instanceof java.sql.Blob) {
                // Convertir le Blob en byte[] puis encoder en base64
                try {
                    java.sql.Blob blob = (java.sql.Blob) value;
                    byte[] bytes = blob.getBytes(1, (int) blob.length());
                    String base64 = Base64.getEncoder().encodeToString(bytes);
                    json.append("\"").append(base64).append("\"");
                } catch (Exception e) {
                    json.append("null");
                }
            } else {
                json.append("\"").append(escapeJson(value.toString())).append("\"");
            }
        }
        
        json.append("}");
        return json.toString();
    }

    private static String escapeJson(String str) {
        if (str == null) return "";
        return str.replace("\\", "\\\\")
                  .replace("\"", "\\\"")
                  .replace("\n", "\\n")
                  .replace("\r", "\\r")
                  .replace("\t", "\\t");
    }

    private static void createQueueTable(Connection conn) throws SQLException {
        String createTable = """
            CREATE TABLE IF NOT EXISTS sync_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                operation ENUM('INSERT', 'UPDATE', 'DELETE', 'TRANSFER') NOT NULL,
                seq VARCHAR(50) NOT NULL,
                col_type VARCHAR(50) NOT NULL,
                data JSON NOT NULL,
                hash_data VARCHAR(32),
                status ENUM('PENDING', 'PROCESSING', 'DONE', 'ERROR') DEFAULT 'PENDING',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                error_message TEXT,
                retry_count INT DEFAULT 0,
                INDEX idx_status (status),
                INDEX idx_seq_coltype (seq, col_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """;
        try (Statement stmt = conn.createStatement()) {
            stmt.execute(createTable);
        }
    }

    private static void createHistoryTable(Connection conn) throws SQLException {
        String createTable = """
            CREATE TABLE IF NOT EXISTS Historique (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(20) NOT NULL,
                seq VARCHAR(50),
                col_type VARCHAR(50),
                titre VARCHAR(500),
                isbn VARCHAR(100),
                old_col_type VARCHAR(50),
                new_col_type VARCHAR(50),
                date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                details TEXT,
                hash_before VARCHAR(32),
                hash_after VARCHAR(32),
                INDEX idx_action (action),
                INDEX idx_date (date_action),
                INDEX idx_seq_coltype (seq, col_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        """;
        try (Statement stmt = conn.createStatement()) {
            stmt.execute(createTable);
        }
    }

    public static void recoverBDD(Properties prop) throws SQLException, ClassNotFoundException {
        System.out.println("\n--- Phase 1: Copie Access -> MySQL Temp ---");
        
        ResultSet rs;
        Statement sAccess, sMysql;
        Connection mysql;

        Connection access = getDBData(prop);
        mysql = "MARIADB".equals(prop.getProperty("TYPEDB"))
            ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAME"))
            : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAME"));

        sAccess = access.createStatement();
        sMysql = mysql.createStatement();

        mysql.createStatement().execute("SET FOREIGN_KEY_CHECKS=0");
        mysql.createStatement().execute("SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        DatabaseMetaData md = mysql.getMetaData();
        ResultSet rsMysql = md.getTables(prop.getProperty("DB_MYSQL_DBNAME"), null, "%", new String[] { "TABLE" });
        while (rsMysql.next()) {
            String table = rsMysql.getString(3);
            if (!"sync_queue".equals(table) && !"Historique".equals(table)) {
                sMysql.executeUpdate("TRUNCATE TABLE " + table);
            }
        }

        rsMysql = md.getTables(prop.getProperty("DB_MYSQL_DBNAME"), null, "%", new String[] { "TABLE" });
        while (rsMysql.next()) {
            String table = rsMysql.getString(3);
            if ("Historique".equals(table) || "sync_queue".equals(table)) continue;

            if ("Traitement".equals(table)) {
                try (PreparedStatement pstmt = mysql.prepareStatement("INSERT INTO Traitement VALUES (?)")) {
                    pstmt.setTimestamp(1, new Timestamp(currentTime.getTime()));
                    pstmt.execute();
                }
                continue;
            }

            rs = "Matiere".equals(table)
                ? sAccess.executeQuery("SELECT * FROM Matière")
                : sAccess.executeQuery("SELECT * FROM " + table);

            ResultSetMetaData meta = rs.getMetaData();
            int columnCount = meta.getColumnCount();
            Set<String> seenColumns = new HashSet<>();
            List<Integer> validIndexes = new ArrayList<>();
            List<String> validColumnNames = new ArrayList<>();

            for (int i = 1; i <= columnCount; i++) {
                String name = meta.getColumnName(i);
                if ("Matiere".equals(table) && "Matière".equals(name)) {
                    name = "Matiere";
                }

                if (seenColumns.add(name)) {
                    validIndexes.add(i);
                    validColumnNames.add(name);
                }
            }

            int rowCount = 0;
            while (rs.next()) {
                int totalParams = validIndexes.size();
                boolean isMonnaie = "Monnaie".equals(table);
                if (isMonnaie) totalParams += 2;

                StringBuilder query = new StringBuilder("INSERT INTO " + table + " (");
                query.append(validColumnNames.stream().map(n -> "`" + n + "`").collect(Collectors.joining(", ")));
                if (isMonnaie) query.append(", Traite, DateLastTraite");
                query.append(") VALUES (").append("?,".repeat(totalParams));
                query.setLength(query.length() - 1);
                query.append(")");
                
                try (PreparedStatement pstmt = mysql.prepareStatement(query.toString())) {
                    int paramIndex = 1;
                    for (int idx : validIndexes) {
                        Object obj = rs.getObject(idx);
                        if (obj == null) {
                            pstmt.setNull(paramIndex++, meta.getColumnType(idx));
                        } else {
                            pstmt.setObject(paramIndex++, obj);
                        }
                    }
                    if (isMonnaie) {
                        pstmt.setInt(paramIndex++, 0);
                        pstmt.setTimestamp(paramIndex, new Timestamp(currentTime.getTime()));
                    }
                    pstmt.execute();
                    rowCount++;
                }
            }
            System.out.println("  Table " + table + " : " + rowCount + " lignes");
        }

        rsMysql.close();
        mysql.createStatement().execute("SET FOREIGN_KEY_CHECKS=1");
        access.close();
        mysql.close();
        
        System.out.println("Phase 1 terminée");
    }

    public static void verifyDataDirect(Properties prop) throws SQLException, ClassNotFoundException, NoSuchAlgorithmException {
        System.out.println("\n--- Phase 2: Comparaison et application directe ---");
        
        try (
            Connection mysql = "MARIADB".equals(prop.getProperty("TYPEDB"))
                ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAME"))
                : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAME"));
            Connection mysqlRef = "MARIADB".equals(prop.getProperty("TYPEDB"))
                ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"))
                : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"));
            Statement sMysql = mysql.createStatement();
        ) {
            mysqlRef.createStatement().execute("SET FOREIGN_KEY_CHECKS=0");
            
            ResultSet rsList = sMysql.executeQuery("SELECT DISTINCT SEQ, COL_TYPE FROM Monnaie WHERE SEQ <> 0");
            while (rsList.next()) {
                String seq = rsList.getString("SEQ");
                String colType = rsList.getString("COL_TYPE");
                String condition = "WHERE Seq = " + seq + " AND COL_TYPE = '" + colType + "'";

                Map<String, Object> rowMain = new HashMap<>();
                Map<String, Object> rowRef = new HashMap<>();
                boolean inMain = false;
                boolean inRef = false;

                // Lire la ligne de la base principale (Access -> MySQL temp)
                try (Statement stmt1 = mysql.createStatement();
                    ResultSet rsMain = stmt1.executeQuery("SELECT * FROM Monnaie " + condition)) {
                    if (rsMain.next()) {
                        inMain = true;
                        ResultSetMetaData meta = rsMain.getMetaData();
                        for (int i = 1; i <= meta.getColumnCount(); i++) {
                            rowMain.put(meta.getColumnName(i).toUpperCase(), rsMain.getObject(i));
                        }
                    }
                }

                // Lire la ligne de la base de référence (état précédent)
                try (Statement stmt2 = mysqlRef.createStatement();
                    ResultSet rsRef = stmt2.executeQuery("SELECT * FROM Monnaie " + condition)) {
                    if (rsRef.next()) {
                        inRef = true;
                        ResultSetMetaData meta = rsRef.getMetaData();
                        for (int i = 1; i <= meta.getColumnCount(); i++) {
                            rowRef.put(meta.getColumnName(i).toUpperCase(), rsRef.getObject(i));
                        }
                    }
                }

                // Comparaison et application directe
                if (inMain && !inRef) {
                    // Nouveau livre - INSERT direct
                    System.out.println("[INSERT] SEQ=" + seq + " COL_TYPE=" + colType + " - " + rowMain.get("PARTICULARITE"));
                    insertOrUpdateRow(mysqlRef, "INSERT", rowMain);
                    insertCount++;

                } else if (inMain && inRef) {
                    // Comparer les hash pour détecter les modifications
                    String hashMain = computeRowHash(rowMain);
                    String hashRef = computeRowHash(rowRef);
                    
                    if (!hashMain.equals(hashRef)) {
                        System.out.println("[UPDATE] SEQ=" + seq + " COL_TYPE=" + colType + " - " + rowMain.get("PARTICULARITE"));
                        insertOrUpdateRow(mysqlRef, "UPDATE", rowMain);
                        updateCount++;
                    }

                } else if (!inMain && inRef) {
                    // Livre supprimé - DELETE logique
                    System.out.println("[DELETE] SEQ=" + seq + " COL_TYPE=" + colType + " - " + rowRef.get("PARTICULARITE"));
                    String delete = "UPDATE Monnaie SET Traite = -1 WHERE Seq = ? AND COL_TYPE = ?";
                    try (PreparedStatement ps = mysqlRef.prepareStatement(delete)) {
                        ps.setString(1, seq);
                        ps.setString(2, colType);
                        ps.execute();
                    }
                    deleteCount++;
                }
            }
            
            mysqlRef.createStatement().execute("SET FOREIGN_KEY_CHECKS=1");
        }
        
        System.out.println("Phase 2 terminée - " + insertCount + " INSERT, " + updateCount + " UPDATE, " + deleteCount + " DELETE");
    }

    private static void insertOrUpdateRow(Connection conn, String mode, Map<String, Object> row) throws SQLException {
        List<String> keys = new ArrayList<>(row.keySet());
        keys.remove("TRAITE");
        keys.remove("DATELASTTRAITE");
        StringBuilder sql = new StringBuilder();

        if ("INSERT".equals(mode)) {
            sql.append("INSERT INTO Monnaie (");
            sql.append(keys.stream().map(k -> "`" + k + "`").collect(Collectors.joining(", ")));
            sql.append(", Traite, DateLastTraite) VALUES (");
            sql.append("?, ".repeat(keys.size()));
            sql.append("?, ?)");
        } else {
            sql.append("UPDATE Monnaie SET ");
            sql.append(keys.stream().map(k -> "`" + k + "` = ?").collect(Collectors.joining(", ")));
            sql.append(", Traite = ?, DateLastTraite = ? WHERE Seq = ? AND COL_TYPE = ?");
        }

        try (PreparedStatement ps = conn.prepareStatement(sql.toString())) {
            int index = 1;
            for (String k : keys) ps.setObject(index++, row.get(k));
            ps.setInt(index++, 0);
            ps.setTimestamp(index++, new Timestamp(currentTime.getTime()));
            if ("UPDATE".equals(mode)) {
                ps.setObject(index++, row.get("SEQ"));
                ps.setObject(index, row.get("COL_TYPE"));
            }
            ps.execute();
        } catch (Exception e) {
            System.err.println("Erreur " + mode + " pour SEQ=" + row.get("SEQ") + " COL_TYPE=" + row.get("COL_TYPE"));
            e.printStackTrace();
        }
    }

    private static void addToQueue(Connection conn, String operation, String seq, String colType, Map<String, Object> data, String hash) throws SQLException {
        String sql = "INSERT INTO sync_queue (operation, seq, col_type, data, hash_data, status) VALUES (?, ?, ?, ?, ?, 'PENDING')";
        
        try (PreparedStatement ps = conn.prepareStatement(sql)) {
            ps.setString(1, operation);
            ps.setString(2, seq);
            ps.setString(3, colType);
            ps.setString(4, mapToJson(data));
            ps.setString(5, hash);
            ps.execute();
            queuedCount++;
        }
    }

    public static void detectTransfers(Properties prop) throws SQLException, ClassNotFoundException {
        System.out.println("\n--- Phase 3: Détection des transferts ---");
        
        try (
            Connection mysqlRef = "MARIADB".equals(prop.getProperty("TYPEDB"))
                ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"))
                : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"))
        ) {
            // Récupérer les suppressions récentes
            String sqlDeletions = "SELECT * FROM sync_queue WHERE operation = 'DELETE' AND status = 'PENDING'";
            
            try (Statement stmt = mysqlRef.createStatement();
                 ResultSet rsDel = stmt.executeQuery(sqlDeletions)) {
                
                while (rsDel.next()) {
                    String seq = rsDel.getString("seq");
                    String colType = rsDel.getString("col_type");
                    String dataJson = rsDel.getString("data");
                    
                    // Parser le JSON pour extraire ISBN et titre
                    String isbn = extractFromJson(dataJson, "CLASSEUR");
                    String titre = extractFromJson(dataJson, "PARTICULARITE");
                    
                    if ((isbn == null || isbn.trim().isEmpty()) && (titre == null || titre.trim().isEmpty())) {
                        continue;
                    }
                    
                    // Chercher si ce livre a été ajouté pour un autre utilisateur
                    StringBuilder sqlSearch = new StringBuilder();
                    sqlSearch.append("SELECT * FROM sync_queue WHERE operation = 'INSERT' AND status = 'PENDING' AND col_type != ? AND (");
                    
                    List<Object> params = new ArrayList<>();
                    params.add(colType);
                    
                    boolean hasCondition = false;
                    if (isbn != null && !isbn.trim().isEmpty()) {
                        sqlSearch.append("data LIKE ?");
                        params.add("%\"CLASSEUR\":\"" + isbn + "\"%");
                        hasCondition = true;
                    }
                    if (titre != null && !titre.trim().isEmpty()) {
                        if (hasCondition) sqlSearch.append(" OR ");
                        sqlSearch.append("data LIKE ?");
                        params.add("%\"PARTICULARITE\":\"" + escapeJson(titre) + "\"%");
                        hasCondition = true;
                    }
                    
                    if (!hasCondition) continue;
                    
                    sqlSearch.append(")");
                    
                    try (PreparedStatement psSearch = mysqlRef.prepareStatement(sqlSearch.toString())) {
                        int idx = 1;
                        for (Object p : params) {
                            psSearch.setObject(idx++, p);
                        }
                        
                        try (ResultSet rsNew = psSearch.executeQuery()) {
                            if (rsNew.next()) {
                                String newColType = rsNew.getString("col_type");
                                String newSeq = rsNew.getString("seq");
                                int deleteId = rsDel.getInt("id");
                                int insertId = rsNew.getInt("id");
                                
                                System.out.println("[TRANSFER] '" + titre + "' : " + colType + " -> " + newColType);
                                
                                // Marquer les deux comme TRANSFER
                                String updateSql = "UPDATE sync_queue SET operation = 'TRANSFER', status = 'PENDING' WHERE id IN (?, ?)";
                                try (PreparedStatement psUpdate = mysqlRef.prepareStatement(updateSql)) {
                                    psUpdate.setInt(1, deleteId);
                                    psUpdate.setInt(2, insertId);
                                    psUpdate.execute();
                                }
                                
                                transferCount++;
                            }
                        }
                    }
                }
            }
        }
        
        System.out.println("Phase 3 terminée - " + transferCount + " transferts détectés");
    }

    private static String extractFromJson(String json, String key) {
        if (json == null) return null;
        
        String searchKey = "\"" + key + "\":";
        int startIdx = json.indexOf(searchKey);
        if (startIdx == -1) return null;
        
        startIdx += searchKey.length();
        
        // Skip whitespace
        while (startIdx < json.length() && Character.isWhitespace(json.charAt(startIdx))) {
            startIdx++;
        }
        
        if (startIdx >= json.length()) return null;
        
        if (json.charAt(startIdx) == '"') {
            // String value
            startIdx++;
            int endIdx = json.indexOf('"', startIdx);
            if (endIdx == -1) return null;
            return json.substring(startIdx, endIdx);
        } else if (json.charAt(startIdx) == 'n' && json.startsWith("null", startIdx)) {
            return null;
        }
        
        return null;
    }

    private static void printStats() {
        System.out.println("\n=== Statistiques ===");
        System.out.println("Insertions détectées : " + insertCount);
        System.out.println("Mises à jour détectées : " + updateCount);
        System.out.println("Suppressions détectées : " + deleteCount);
        System.out.println("Transferts détectés : " + transferCount);
        System.out.println("Total dans la queue : " + queuedCount);
        System.out.println("====================");
    }
}
