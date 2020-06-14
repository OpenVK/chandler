<?php declare(strict_types=1);

function chandler_escape_url(string $url): string
{
    return preg_replace("%\.\.\/|\/\.\.%", "", $url);
}
