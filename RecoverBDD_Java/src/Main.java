import java.sql.*;
import java.util.*;
import java.util.stream.Collectors;
import java.io.*;
import javax.mail.*;
import javax.mail.internet.*;
import javax.activation.*;
import net.ucanaccess.jdbc.UcanaccessDriver;

public class Main {
    final static Calendar calendar = Calendar.getInstance();
    final static java.util.Date currentTime = calendar.getTime();

    public static void sendEmail(Properties prop, String error) throws UnsupportedEncodingException {
        String userEmail = prop.getProperty("EMAIL_USER");
        String password = prop.getProperty("EMAIL_PASSWORD");
        String sender = prop.getProperty("EMAIL");
        String toEmail = prop.getProperty("EMAIL_TO");

        String content = "Bonjour\nUne erreur s'est produite lors de l'exécution du script JAVA RecoverBDD-Books.\nVoici l'erreur :\n" + error;

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
            message.setFrom(new InternetAddress(sender, "RecoverBDD-BD"));
            message.setSubject("Erreur RecoverBDD-BD");
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
        return DriverManager.getConnection("jdbc:mysql://" + prop.getProperty("DB_MYSQL_SERVER") + ":" + prop.getProperty("DB_MYSQL_PORT") + "/" + database,
                prop.getProperty("DB_MYSQL_USER"), prop.getProperty("DB_MYSQL_PASSWORD"));
    }

    public static Connection getDBMaria(Properties prop, String database) throws SQLException, ClassNotFoundException {
        Class.forName("org.mariadb.jdbc.Driver");
        return DriverManager.getConnection("jdbc:mariadb://" + prop.getProperty("DB_MYSQL_SERVER") + ":" + prop.getProperty("DB_MYSQL_PORT") + "/" + database,
                prop.getProperty("DB_MYSQL_USER"), prop.getProperty("DB_MYSQL_PASSWORD"));
    }

    public static void main(String[] args) throws SQLException, ClassNotFoundException, IOException {
        String configFilePath = "config/config.properties";
        FileInputStream propsInput = new FileInputStream(configFilePath);
        Properties prop = new Properties();
        prop.load(propsInput);

        try {
            recoverBDD(prop);
            verifyData(prop);
        } catch (Exception e) {
            sendEmail(prop, e.getMessage());
			            e.printStackTrace();

        }
    }

    public static void recoverBDD(Properties prop) throws SQLException, ClassNotFoundException {
        ResultSet rs;
        Statement sAccess, sMysql;
        Connection mysql;

        Connection access = getDBData(prop);
        mysql = "MARIADB".equals(prop.getProperty("TYPEDB"))
            ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAME"))
            : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAME"));

        sAccess = access.createStatement();
        sMysql = mysql.createStatement();

       
        Connection mysqlRef = "MARIADB".equals(prop.getProperty("TYPEDB"))
                ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"))
                : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"));
        Statement stmt = mysqlRef.createStatement();
        Statement sMysqlRef = mysqlRef.createStatement();

        mysql.createStatement().execute("SET FOREIGN_KEY_CHECKS=0");
        mysql.createStatement().execute("SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        DatabaseMetaData md = mysql.getMetaData();
        ResultSet rsMysql = md.getTables(prop.getProperty("DB_MYSQL_DBNAME"), null, "%", new String[] { "TABLE" });
        while (rsMysql.next()) {
            String table = rsMysql.getString(3);
            sMysql.executeUpdate("TRUNCATE TABLE " + table);
        }

        rsMysql = md.getTables(prop.getProperty("DB_MYSQL_DBNAME"), null, "%", new String[] { "TABLE" });
        while (rsMysql.next()) {
            String table = rsMysql.getString(3);
            if ("Historique".equals(table)) continue;

            if ("Traitement".equals(table)) {
                try (PreparedStatement pstmt = mysql.prepareStatement("INSERT INTO Traitement VALUES (?)")) {
                    pstmt.setTimestamp(1, new Timestamp(currentTime.getTime()));
                    pstmt.execute();
                }
                try (PreparedStatement pstmtRef = mysqlRef.prepareStatement("INSERT INTO Traitement VALUES (?)")) {
                    sMysqlRef.executeUpdate("TRUNCATE TABLE Traitement");
                    pstmtRef.setTimestamp(1, new Timestamp(currentTime.getTime()));
                    pstmtRef.execute();
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
					name = "Matiere"; // 🔁 correction spécifique uniquement dans cette table
				}

				if (seenColumns.add(name)) {
					validIndexes.add(i);
					validColumnNames.add(name);
				}
			}

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
                }
            }
        }

        rsMysql.close();
        mysql.createStatement().execute("SET FOREIGN_KEY_CHECKS=1");
        access.close();
        mysql.close();

        mysqlRef.createStatement().execute("SET FOREIGN_KEY_CHECKS=1");
        mysqlRef.close();
    }

    public static void verifyData(Properties prop) throws SQLException, ClassNotFoundException, UnsupportedEncodingException {
        try (
            Connection mysql = "MARIADB".equals(prop.getProperty("TYPEDB"))
                ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAME"))
                : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAME"));
            Connection mysqlRef = "MARIADB".equals(prop.getProperty("TYPEDB"))
                ? getDBMaria(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"))
                : getDBMysql(prop, prop.getProperty("DB_MYSQL_DBNAMEREF"));
            Statement stmt = mysqlRef.createStatement();
            Statement sMysql = mysql.createStatement();
            Statement sMysqlRef = mysqlRef.createStatement()
        ) {
            stmt.execute("SET FOREIGN_KEY_CHECKS=0");
            stmt.execute("SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

            // 🔽 On compare si des éléments on été ajoutés ou modifié
            ResultSet rsList = sMysql.executeQuery("SELECT DISTINCT SEQ, COL_TYPE FROM Monnaie WHERE SEQ <> 0");
		    while (rsList.next()) {
				String seq = rsList.getString("SEQ");
				String colType = rsList.getString("COL_TYPE");
				String condition = "WHERE Seq = " + seq + " AND COL_TYPE = '" + colType + "'";

				// 🔽 On prépare les maps
				Map<String, Object> rowMain = new HashMap<>();
				Map<String, Object> rowRef = new HashMap<>();
				boolean inMain = false;
				boolean inRef = false;

				// 🔽 On lit la ligne de la base principale
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

				// 🔽 On lit la ligne de la base de référence
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

				// 🔍 Comparaison et traitement
				if (inMain && !inRef) {
					System.out.println("[INSERT] " + seq + " - " + colType);
					insertOrUpdateRow(mysqlRef, "INSERT", rowMain);

				} else if (inMain && inRef) {
					boolean different = false;
					Set<String> commonKeys = new HashSet<>(rowMain.keySet());
					commonKeys.retainAll(rowRef.keySet()); // Ne compare que les colonnes présentes dans les deux
					commonKeys.remove("TRAITE");
					commonKeys.remove("DATELASTTRAITE");
					
					for (String col : commonKeys) {
						Object v1 = rowMain.get(col);
						Object v2 = rowRef.get(col);
						if (v1 instanceof byte[] && v2 instanceof byte[]) {
							if (!Arrays.equals((byte[]) v1, (byte[]) v2)) {
								different = true;
								break;
							}
						} else if (!Objects.equals(v1, v2)) {
							different = true;
							break;
						}
					}
					if (different) {
						System.out.println("[UPDATE] " + seq + " - " + colType);
						insertOrUpdateRow(mysqlRef, "UPDATE", rowMain);
					}

				} else {
					sendEmail(prop, "Erreur : entrée introuvable dans les deux bases (SEQ=" + seq + ", COL_TYPE=" + colType + ")");
				}
			}

		    // 🔽 On compare si des élements on disparu
		    ResultSet rsListRef = sMysqlRef.executeQuery("SELECT DISTINCT SEQ, COL_TYPE FROM Monnaie WHERE SEQ <> 0");
		    while (rsListRef.next()) {
				String seq = rsListRef.getString("SEQ");
				String colType = rsListRef.getString("COL_TYPE");
				String condition = "WHERE Seq = " + seq + " AND COL_TYPE = '" + colType + "'";

				// 🔽 On prépare les maps
				Map<String, Object> rowMain = new HashMap<>();
				Map<String, Object> rowRef = new HashMap<>();
				boolean inMain = false;
				boolean inRef = false;

				// 🔽 On lit la ligne de la base principale
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

				// 🔽 On lit la ligne de la base de référence
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

				// 🔍 Comparaison et traitement
				if (!inMain && inRef) {
					System.out.println("[DELETE logique] " + seq + " - " + colType);
					String delete = "UPDATE Monnaie SET Traite = -1 WHERE Seq = ? AND COL_TYPE = ?";
					try (PreparedStatement ps = mysqlRef.prepareStatement(delete)) {
						ps.setString(1, seq);
						ps.setString(2, colType);
						ps.execute();
					}

				}
			}

            stmt.execute("SET FOREIGN_KEY_CHECKS=1");
        }
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
			e.printStackTrace();

        }
    }
}
