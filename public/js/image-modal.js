/**
 * Gestion de la modale d'images pour les couvertures de livres et magazines
 */
$(document).ready(function () {
    var modal = document.getElementById("myModal");
    
    if (!modal) return;
    
    var modalImg = document.getElementById("img01");
    var captionText = document.getElementById("caption");

    // Fermer la modale en cliquant sur le X
    $('.image-modal-close').click(function() {
        modal.style.display = "none";
    });

    // Fermer la modale en cliquant en dehors de l'image
    $('#myModal').click(function (event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });

    // Ouvrir la modale en cliquant sur une image de couverture
    $('.img-cover, .clickable-image').click(function (event) {
        var image = event.target;
        if (image) {
            modal.style.display = "block";
            modalImg.src = image.src;
            captionText.innerHTML = image.alt;
        }
    });

    // Fermer la modale avec la touche Echap
    $(document).keydown(function(e) {
        if (e.keyCode === 27) {
            modal.style.display = "none";
        }
    });
});
