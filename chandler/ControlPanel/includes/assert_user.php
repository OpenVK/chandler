<?php declare(strict_types=1);

return (function(): void
{
    $result = (require(__DIR__ . "/verify_user.php"))();
    if(is_null($result)) {
        header("HTTP/1.1 307 Internal Redirect");
        header("Location: /chandlerd/login");
        exit;
    } else if(!$result) {
        chandler_http_panic(403, "Bruh moment", "You are not allowed to look at the admin panel");
    }
});