<?php declare(strict_types=1);
namespace Chandler\Database;
use Chandler\Database\DatabaseConnection;
use Nette\Database\Table\Selection;
use Nette\Database\Table\ActiveRow;
use Nette\InvalidStateException as ISE;


abstract class DBEntity
{
    protected $record;
    protected $changes;
    protected $deleted;
    
    protected $tableName;
    
    function __construct(?ActiveRow $row = NULL)
    {
        if(is_null($row)) return;
        
        $_table = $row->getTable()->getName();
        if($_table !== $this->tableName)
            throw new ISE("Invalid data supplied for model: table $_table is not compatible with table" . $this->tableName);
        
        $this->record = $row;
    }
    
    function __call(string $fName, array $args)
    {
        if(substr($fName, 0, 3) === "set") {
            $field = mb_strtolower(substr($fName, 3));
            $this->stateChanges($field, $args[0]);
        } else {
            throw new \Error("Call to undefined method " . get_class($this) . "::$fName");
        }
    }
    
    private function getTable(): Selection
    {
        return DatabaseConnection::i()->getContext()->table($this->tableName);
    }
    
    protected function getRecord(): ?ActiveRow
    {
        return $this->record;
    }
    
    protected function stateChanges(string $column, $value): void
    {
        if(!is_null($this->record))
            $t = $this->record->{$column}; #Test if column exists
        
        $this->changes[$column] = $value;
    }
    
    function getId(): int
    {
        return $this->getRecord()->id;
    }
    
    function isDeleted(): bool
    {
        return (bool) $this->getRecord()->deleted;
    }
    
    function unwrap(): object
    {
        return (object) $this->getRecord()->toArray();
    }
    
    function delete(bool $softly = true): void
    {
        if(is_null($this->record))
            throw new ISE("Can't delete a model, that hasn't been flushed to DB. Have you forgotten to call save() first?");
        
        if($softly) {
            $this->record = $this->getTable()->where("id", $this->record->id)->update(["deleted" => true]);
        } else {
            $this->record->delete();
            $this->deleted = true;
        }
    }
    
    function undelete(): void
    {
        if(is_null($this->record))
            throw new ISE("Can't undelete a model, that hasn't been flushed to DB. Have you forgotten to call save() first?");
        
        $this->getTable()->where("id", $this->record->id)->update(["deleted" => false]);
    }
    
    function save(): void
    {
        if(is_null($this->record)) {
            $this->record = $this->getTable()->insert($this->changes);
        } else if($this->deleted) {
            $this->record = $this->getTable()->insert((array) $this-->record);
        } else {
            $this->record->getTable()->where("id", $this->record->id)->update($this->changes);
            $this->record = $this->getTable()->get($this->record->id);
        }
        
        $this->changes = [];
    }
    
    use \Nette\SmartObject;
}
