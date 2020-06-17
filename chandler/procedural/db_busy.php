<?php declare(strict_types=1);

/**
 * Ends application and renders DB error page.
 * 
 * @api
 * @author kurotsun <celestine@vriska.ru>
 * @return void
 */
function chandler_db_busy(): void
{
    $errPage = <<<'EOE'
<html>
    <head>
        <style>body {
            font-family: sans-serif;
            position: relative;
            height: 100vh;
            overflow: hidden;
            }
            #dbErrorBody {
            position: absolute;
            top: 50%;
            left: 50%;
            margin-right: -50%;
            transform: translate(-50%, -50%);
            width: 400px;
            text-align: center;
            }
            #dbErrorBody h1 {
            margin-top: 5px;
            margin-bottom: 2px;
            }
            #dbErrorBody span {
            color: grey;
            }
            #dbErrorBody img {
            max-width: 128px;
            }
        </style>
        <title>Resource Busy | Chandler App Server</title>
    </head>
    <body>
        <div id="dbErrorBody">
            <img src="https://i.imgur.com/N2Ix4fI.png" alt="Database Error">
            <h1>Service Unavailable</h1>
            <span>Server can't proccess your request at this moment. Please try again later. Sorry for that.</span>
        </div>
    </body>
</html>
EOE;
    
    header("HTTP/1.1 503 Service Unavailable");
    exit($errPage);
}
