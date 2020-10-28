<?php
namespace GDO\DB;

use GDO\Core\GDT;
use GDO\UI\WithLabel;

/**
 * A virtual field that is generated by a subquery.
 * 
 * @author gizmore
 * @version 6.10
 * @since 6.10
 * 
 * @see GDT_Join
 */
final class GDT_Virtual extends GDT
{
    use WithLabel;
    use WithDatabase;
    
    public function __construct()
    {
        $this->virtual = true;
    }
    
    #############
    ### Query ###
    #############
    public $subquery;
    public function subquery($subquery) { $this->subquery = $subquery; return $this; }
    
    #############
    ### Event ###
    #############
    public function gdoBeforeRead(Query $query) { $query->select("({$this->subquery}) {$this->name}"); }
    public function getGDOData() {} # virtual
    
    #############
    ### Proxy ###
    #############
    /** @var $gdtType GDT **/
    public $gdtType;
    private function proxy() { return $this->gdtType->gdo($this->gdo)->label($this->label, $this->labelArgs); }
    public function gdtType($className) { $this->gdtType = new $className(); $this->gdtType->name = $this->name; return $this; }
    
    ##############
    ### Render ###
    ##############
    public function renderCell() { return $this->proxy()->renderCell(); }
    public function renderCard() { return $this->proxy()->renderCard(); }
    public function renderForm() { return $this->proxy()->renderForm(); }
}
