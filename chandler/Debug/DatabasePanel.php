<?php declare(strict_types=1);
namespace Chandler\Debug;
use Nette\Database\Helpers as DbHelpers;

class DatabasePanel implements \Tracy\IBarPanel
{
    use \Nette\SmartObject;
    
    public function getTab()
    {
        $count = sizeof($GLOBALS["dbgSqlQueries"]);
        $time  = ceil($GLOBALS["dbgSqlTime"] * 1000);
        $svg   = file_get_contents(__DIR__ . "/templates/db-icon.svg");
        
        return <<<EOF
        <span title="DB Queries">
            $svg
            <span class="tracy-label">$count queries ($time ms)</span>
        </span>
EOF;
    }
    
    public function getPanel()
    {
        $html = <<<HTML
        <h1>Queries:</h1>
        <div class="tracy-inner">
        <div class="tracy-inner-container">
            <table class="tracy-sortable">
HTML;
        
        foreach($GLOBALS["dbgSqlQueries"] as $query) {
            $query = DbHelpers::dumpSql($query);
            $html .= "<tr><td>$query</td></tr>";
        }
        
        $html .= "</table></div></div>";
        
        return $html;
    }
}