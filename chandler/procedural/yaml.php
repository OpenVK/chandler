<?php

/**
 * @param string $filePath
 *
 * @return array
 */
function chandler_parse_yaml(string $filePath): array
{
    return yaml_parse_file($filePath);
}
