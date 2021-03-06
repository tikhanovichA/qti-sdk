<?php
namespace qtismtest\data\content\xhtml;

use qtismtest\QtiSmTestCase;
use qtism\data\content\xhtml\Object;

class ObjectTest extends QtiSmTestCase
{
    public function testCreateWrongData()
    {
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'data' argument must be a URI or an empty string, 'integer' given."
        );
        
        new Object(999, 'image/png');
    }
    
    public function testCreateWrongType()
    {
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'type' argument must be a non-empty string, 'integer' given."
        );
        
        new Object('./my-image.png', 999);
    }
    
    public function testSetWidthWrongType()
    {
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'width' argument must be an integer, 'double' given."
        );
        
        $object = new Object('./my-image.png', 'image/png');
        $object->setWidth(999.999);
    }
    
    public function testSetHeightWrongType()
    {
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'height' argument must be an integer, 'double' given."
        );
        
        $object = new Object('./my-image.png', 'image/png');
        $object->setHeight(999.999);
    }
}
