<?php

/**
 * Ends application and renders error page.
 * 
 * @api
 * @author kurotsun <celestine@vriska.ru>
 * @param int $code HTTP Error code
 * @param string $description HTTP Error description
 * @param string $message Additional message to show to client
 * @return void
 */
function chandler_http_panic(int $code = 400, string $description = "Bad Request", string $message = ""): void
{
    $error = <<<EOE
<?xml version="1.0" encoding="UTF-8" ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
    <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
    <style>
    body,h1,h2,h3{margin:0;}
    body{font-family:sans-serif;background-color:#cee3ef;}
    fieldset{border-color:#73716b;}
    legend{font-weight:900;background-color:#e7eff7;border:2px solid #e0e0e0;border-bottom:2px solid #949694;}
    h2,h3{color:#ce0000;}
    h2{margin-bottom:10px;}
    #header,#subheader_Server{color:#fff;padding:20px;}
    #header{background-color:#5a86b5;border-bottom:1px solid #4a6d8c;}
    #subheader_Server{background-color:#5a7da5;text-align:right;border-bottom: 1px solid #c6cfde;}
    .container{margin:20px;padding:10px;background-color:#fff;}
    </style>
    <title></title>
</head>
<body>
    <div id="header">
        <h1>Server error</h1>
    </div>
    <div id="subheader_Server">
        libchandler
    </div>
    <div class="container">
        <fieldset>
            <legend>Error summary</legend>
            
            <h2>HTTP Error $code.0 - $description</h2>
            <h3>$message</h3>
        </fieldset>
    </div>
</body>
</html> 

EOE;
    
    header("HTTP/1.0 $code $description");
    exit($error);
}