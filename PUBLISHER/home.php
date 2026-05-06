<?php
ini_set('display_errors',1); 
error_reporting(E_ALL);

require_once "php/config.php";
require_once "php/db.php";
require_once "php/utils.php";
require_once "php/controls.php";
require_once "php/start.php";
require_once "php/session.php";


$t = [];
//$lang = $user_language;






?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title><?php echo app::$project_name; ?></title>        
        
        <?php include "_head.php"; ?>

        <script>
            function refresh() {
                window.location.href =  "<?php echo app::$host; ?>";
            }
        </script>

        <style>

            .home-html {
                margin-bottom:30px;
            }

            .blocks-grid {
                display:grid;
                grid-template-columns: repeat(3, 1fr);
                column-gap: 20px;
                row-gap:20px;
                margin-bottom:20px;
            }
            @media (max-width:1000px) {
                .blocks-grid {
                    grid-template-columns: 48% 48%;                    
                }
            }
            @media (max-width:700px) {
                .blocks-grid {
                    grid-template-columns: 100%;                    
                }
            }

            .home-block {
                padding:20px;
                background-color:#fff;
                box-shadow: 0px 0px 5px #666;
                border-radius:10px;
            } 


        </style>
        
               
    </head>
    
    <body class="home">
        
        <?php include "blocks/header.php"; ?>
        
        <div class="padding-20">
            <!--<h2 class="home">Hello <?php print $user_fullname; ?>!</h2>-->
            <?php //if ($user->home_html()!="") { ?> 
            <div class="home-html">
                <?php //echo $user->home_html() ?>
            </div>
            <?php //} ?>

            <div class="blocks-grid">

                <?php //if ($user->block1_html()!="") { ?> 
                <div class="home-block">
                    <?php //echo $user->block1_html() ?>
                </div>
                <?php //} ?>

                <?php //if ($user->block2_html()!="") { ?> 
                <div class="home-block">
                    <?php //echo $user->block2_html() ?>
                </div>
                <?php //} ?>

                <?php //if ($user->block3_html()!="") { ?> 
                <div class="home-block">
                    <?php //echo $user->block3_html() ?>
                </div>
                <?php //} ?>

            </div>

            


        </div>
        
        <?php include "blocks/footer.php"; ?>
    </body>
    
</html>