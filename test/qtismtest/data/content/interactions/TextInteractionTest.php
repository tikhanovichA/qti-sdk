<?php
namespace qtismtest\data\content\interactions;

use qtismtest\QtiSmTestCase;
use qtism\data\content\interactions\TextEntryInteraction;

class TextInteractionTest extends QtiSmTestCase
{
    public function testSetBaseWrongType()
    {
        $textEntryInteraction = new TextEntryInteraction('RESPONSE');
        
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'base' argument must be a positive (>= 0) integer value, 'boolean' given."
        );
        
        $textEntryInteraction->setBase(true);
    }
    
    public function testSetStringIdentifierWrongType()
    {
        $textEntryInteraction = new TextEntryInteraction('RESPONSE');
        
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'stringIdentifier' argument must be a valid QTI identifier or an empty string, '1' given."
        );
        
        $textEntryInteraction->setStringIdentifier(true);
    }
    
    public function testSetExpectedLengthWrongType()
    {
        $textEntryInteraction = new TextEntryInteraction('RESPONSE');
        
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'expectedLength' argument must be a strictly positive (> 0) integer or -1, 'boolean' given."
        );
        
        $textEntryInteraction->setExpectedLength(true);
    }
    
    public function testSetPatternMaskWrongType()
    {
        $textEntryInteraction = new TextEntryInteraction('RESPONSE');
        
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'patternMask' argument must be a string value, 'boolean' given."
        );
        
        $textEntryInteraction->setPatternMask(true);
    }
    
    public function testSetPlaceholderTextWrongType()
    {
        $textEntryInteraction = new TextEntryInteraction('RESPONSE');
        
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The 'placeholderText' argument must be a string value, 'boolean' given."
        );
        
        $textEntryInteraction->setPlaceholderText(true);
    }
}
