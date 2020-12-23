<?php
namespace GDO\UI;

/**
 * Flex class handling
 * @author gizmore
 * @version 6.10
 * @since
 * @see GDT_Bar
 * @see GDT_Container
 */
trait WithFlex
{
    public $flex = false;
    public $flexDirection = 'row';
    public function flex($flex=true) { $this->flex = $flex; return $this; }
    public function vertical() { $this->flexDirection = 'column'; return $this->flex(); }
    public function horizontal() { $this->flexDirection = 'row'; return $this->flex(); }
    public function htmlDirection() { return $this->flexDirection === 'row' ? 'row' : 'column'; }

}