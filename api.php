<?php

echo "# API\n\n```php\n<?php\n\nnamespace Dop;\n";

echo api('src/Dop/Connection.php');
echo api('src/Dop/Fragment.php');
echo api('src/Dop/Result.php');
echo api('src/Dop/Exception.php');

echo "```\n";

function api($file)
{
    $output = file_get_contents($file);
    $output = preg_replace("(<\?php\s+namespace\s+.*?\s*;\s*)s", "\n", $output);
    $output = preg_replace("(\)\s*{.*?\n    }\n)s", ");\n", $output);

    while ($i = strpos($output, 'protected')) {
        $j = strrpos($output, '/*', $i - strlen($output));
        $k = strpos($output, ';', $i);
        $output = substr($output, 0, $j) . substr($output, $k + 1);
    }

    $output = preg_replace("( +\n)s", "\n", $output);
    $output = preg_replace("(\n\n+)s", "\n\n", $output);
    $output = preg_replace("(\s+//(\s*)})s", "\\1}", $output);

    return $output;
}
