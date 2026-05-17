<?php

?>

<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
<meta name="color-scheme" content="light dark">

<script>
    (function() {
        var storageKey = 'publisher-theme';
        var storedTheme = null;
        try {
            storedTheme = localStorage.getItem(storageKey);
        } catch (error) {
            storedTheme = null;
        }

        var preferredTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        var theme = storedTheme === 'dark' || storedTheme === 'light' ? storedTheme : preferredTheme;
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.style.colorScheme = theme;
    })();
</script>

<link rel="icon" type="image/png" href="img/favicon.png" sizes="32x32">
<link rel="icon" type="image/png" href="img/favicon.png" sizes="64x64">


<script type="text/javascript" src="//code.jquery.com/jquery-latest.min.js"></script>
<!--<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>-->

<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">

<!-- Optional theme -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap-theme.min.css">

<!-- Latest compiled and minified JavaScript -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>

<link href="css/grid.css" rel="stylesheet" type="text/css" />
<link href="css/menu.css" rel="stylesheet" type="text/css" />
<link href="css/style.css" rel="stylesheet" type="text/css" />
<link href="css/global_styles.css" rel="stylesheet" type="text/css" />
<link href="css/theme.css" rel="stylesheet" type="text/css" />

<!-- Latest font-awesome 4.7.0 -->
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.css" rel="stylesheet" type="text/css" />
<script src="https://kit.fontawesome.com/e46bc4bd00.js" crossorigin="anonymous"></script>

<link rel="stylesheet" href="//code.jquery.com/ui/1.10.4/themes/smoothness/jquery-ui.css" />
<script type="text/javascript" src="js/jquery.easing.1.3.js"></script>
<script type="text/javascript" src="js/jquery.cookie.js"></script>       

<script src="//code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
<script type="text/javascript" src="js/jquery.ui.datepicker-gr.js"></script>
<script type="text/javascript" src="js/functions.js"></script>
<script type="text/javascript" src="js/code.js"></script>

<script src="js/highcharts.js"></script>
<script src="js/exporting.js"></script>

<!--<link rel="stylesheet" type="text/css" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/ui-lightness/jquery-ui.css">-->
<script src="js/evol-colorpicker.min.js" type="text/javascript" charset="utf-8"></script>        
<link href="css/evol-colorpicker.css" rel="stylesheet" type="text/css">


<link href="css/tableexport.css" rel="stylesheet" type="text/css">
<script src="js/FileSaver.min.js"></script>
<script src="js/Blob.min.js"></script>
<script src="js/xls.core.min.js"></script>
<script src="js/tableexport.js"></script>

<script src="js/jquery.tablesorter.js"></script>

<!--<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.4.1/jspdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/2.3.5/jspdf.plugin.autotable.min.js"></script>
<script src="js/tableHTMLExport.js"></script>

<script src="jsPDF/libs/jspdf.umd.js"></script>
<script src="jsPDF/dist/jspdf.plugin.autotable.js"></script>-->

<script src="js/tinymce/tinymce.min.js"></script>


<style>
    
    img {
        max-width: 100%;
        height: auto;
    }
    
    input, textarea, select {
        margin-bottom: 0.5em !important;
    }
    
    .main {
        padding:0px 1em;
    }
    
    a {
        cursor:pointer;
    }

    h1 {
        margin-top:0px;
        margin-bottom:20px;
    }
    h2, h3 {
        margin-top:0px;
        margin-bottom:10px;
    }
    
    .form-control {
        display:inline;
    }
    
    .table-footer {
        text-align: right;
        font-weight: bold;
    }

    .main-container{
        display: flex;
    }

    .left-nav{                
        width: 240px;
        padding: 20px;
        float: left;
        border-right: 2px solid #ccc;
    }

    .form-container {
        width: calc(100% - 220px);
        min-height: 100Vh;
        padding: 20px;
        float: left;
    }

    .left-nav .btn-primary {
        width: 100%;
        margin: 0 0 10px 0;
        white-space: pre-wrap; /* CSS3 */    
        white-space: -moz-pre-wrap; /* Mozilla, since 1999 */
        white-space: -pre-wrap; /* Opera 4-6 */    
        white-space: -o-pre-wrap; /* Opera 7 */    
        word-wrap: break-word; /* Internet Explorer 5.5+ */
    }

    .left-nav .supplier-name,
    .left-nav .customer-name{
        margin-bottom: 20px;  
        font-size: 20px;  
    }

    .left-nav .supplier-name hr,
    .left-nav .customer-name hr{
        margin-top: 10px;
        margin-bottom: 10px;    
    }

    .website-grid {
        display:grid;grid-template-columns:300px 1fr;gap:30px;
    }
    .website-left-menu {
        background-color:#eee; font-size:20px; line-height:30px; padding:30px 15px;
    }
    .website-main {
        height:calc(100vh - 50px); overflow-y:scroll; 
        padding:30px 30px 50px 0px;
    }

    .tox-tinymce {
        margin-bottom:15px !important;
    }

    .btn-pill {
        padding: 5px 10px;
        background: #fff;
        border: 1px solid #666;
        border-radius: 20px;
        display:inline-block;
        margin-top:10px;
        color:#000;
    }
    .btn-pill:hover {
        background: #000;
        color:#fff;
        text-decoration: none;
    }

    td.active {
        background-color: #fff !important;
    }

    .left-menu-buttons {
        display:none;
    }

    .white {
        color:#fff;
    }


    @media (max-width:600px) {


        .navbar-default {
            margin-bottom:0px;
        }

        .website-grid {
            grid-template-columns: 100%;
        }

        .left-menu {
            display:none;
        }

        .desktop-only {
            display:none;
        }

        .website-main {
            padding:20px;
        }

        .website-left-menu {
            padding:13px 20px;
        }

        .left-menu-buttons {
            display:block;
        }

        .website-main {
            height:auto;
        }

        .website-main #submit-button {
            /* top: 65px;
            right: 20px; */
            opacity:0;
        }

        .website-main #featured_image_img {
            max-width: 100%;
        }

        #default_lang-container .col-4.col-sm-12,
        #active-container .col-4.col-sm-12,
        #services-container .col-4.col-sm-12,
        #products-container .col-4.col-sm-12,
        #food_menu-container .col-4.col-sm-12,
        #rooms-container .col-4.col-sm-12,
        #reviews-container .col-4.col-sm-12,
        #blog-container .col-4.col-sm-12,
        #photo_gallery-container .col-4.col-sm-12,
        #contact_form-container .col-4.col-sm-12,
        #newsletter-container .col-4.col-sm-12,
        #clients-container .col-4.col-sm-12 {
            width:80%;
        }
        #default_lang-container .col-8.col-sm-12,
        #active-container .col-8.col-sm-12,
        #services-container .col-8.col-sm-12,
        #products-container .col-8.col-sm-12,
        #food_menu-container .col-8.col-sm-12,
        #rooms-container .col-8.col-sm-12,
        #reviews-container .col-8.col-sm-12,
        #blog-container .col-8.col-sm-12,
        #photo_gallery-container .col-8.col-sm-12,
        #contact_form-container .col-8.col-sm-12,
        #newsletter-container .col-8.col-sm-12,
        #clients-container .col-8.col-sm-12 {
            width:20%;
        }



    }
    
    
    
    <?php
    //user custom display
    
    if ($bgColor!="") {        
        echo <<<EOT
        body {
            background-color: $bgColor;
        }        
EOT;
   } 
   
   if ($textColor!="") {        
        echo <<<EOT
        body {
            color: $textColor;
        }        
EOT;
   } 
   
   if ($bgColorHeader!="") {        
        echo <<<EOT
        .navbar, .dropdown-menu {
            background-image: none;
            background-color: $bgColorHeader;
        }        
EOT;
   } 
   
   if ($textColorHeader!="") {        
        echo <<<EOT
        .navbar, .navbar a {
            color: $textColorHeader !important;
        }        
EOT;
   }
   
   //$homeBgImg
   if ($homeBgImg!="") {        
        echo <<<EOT
        body.home {
            background-image:url($homeBgImg);
            background-size:cover;
            background-position:center;
            background-repeat:repeat;
            min-height:100vh;
        }
        h2.home {
            color:#fff;
            text-shadow:0px 0px 5px #000;
        }
EOT;
   }
   
   
   if ($userCSS!="") {        
        echo <<<EOT
        $userCSS        
EOT;
   } 
     
     
   
   
   
   ?>


/** element styles */
.tox-promotion {
    display:none;
}
    
    
    
    
</style>


<script>
        
function updateImgPath(elementid, imageUrl) {
    
    var element = document.getElementById(elementid+'_txt');
    var img_element = document.getElementById(elementid+'_img');
    if (element) {
        element.value = imageUrl;
        //update img_element / css / background image
        if (img_element) {
            // 2a. If it's an <img>, update its src
            if (img_element.tagName.toLowerCase() === 'img') {
                img_element.src = imageUrl;
            }

            // 2b. Always update its background-image CSS
            img_element.style.backgroundImage    = `url("${imageUrl}")`;
            img_element.style.backgroundSize     = 'contain';
            img_element.style.backgroundPosition = 'center';

            // 3. Update the parent <a> href, if any
            var parentLink = img_element.closest('a');
            if (parentLink) {
                parentLink.href = imageUrl;
            } else {
                console.warn('No parent <a> found for:', elementid + '_img');
            }
        } else {
            console.warn('Image element not found:', elementid + '_img');
        }
        alert('Element updated');
        try {
            document.getElementById("btn-close-popup-photo").click();
        } catch (error) {
            console.log("btn-close-popup-photo not found");
        }
        try {
            document.getElementById("btn-close-popup-image_path").click();
        } catch (error) {
            console.log("btn-close-popup-image_path not found");
        }
        
        
        
    }
    else {
        alert('Element not found');
    }
}

</script>


<script>

    $(function() {

        $(".btn-show-options").click(function() {
            $(".left-menu").show();
            $(this).hide();
            $(".btn-hide-options").show();
        });

        $(".btn-hide-options").click(function() {
            $(".left-menu").hide();
            $(this).hide();
            $(".btn-show-options").show();
        });

        $(".btn-click-save").click(function() {
            $(".website-main form").submit();
        });

    });


</script>
