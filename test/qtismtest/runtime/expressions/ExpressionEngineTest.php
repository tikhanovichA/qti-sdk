<?php
namespace qtismtest\runtime\expressions;

use qtismtest\QtiSmTestCase;
use qtism\runtime\expressions\ExpressionEngine;
use qtism\data\ItemSessionControl;

class ExpressionEngineTest extends QtiSmTestCase
{	
	public function testExpressionEngineBaseValue()
    {
		$expression = $this->createComponentFromXml('<baseValue baseType="duration">P2D</baseValue>');
		$engine = new ExpressionEngine($expression);
		$result = $engine->process();
		$this->assertInstanceOf('qtism\\common\\datatypes\\QtiDuration', $result);
		$this->assertEquals(2, $result->getDays());
	}
	
	public function testExpressionEngineSum()
    {
		$expression = $this->createComponentFromXml('
			<sum> <!-- 60 -->
				<product> <!-- 50 -->
					<baseValue baseType="integer">10</baseValue>
					<baseValue baseType="integer">5</baseValue>
				</product>
				<divide> <!-- 10 -->
					<baseValue baseType="integer">50</baseValue>
					<baseValue baseType="integer">5</baseValue>
				</divide>
			</sum>
		');
		
		$engine = new ExpressionEngine($expression);
		$result = $engine->process();
		$this->assertInstanceOf('qtism\\common\\datatypes\\QtiFloat', $result);
		$this->assertEquals(60.0, $result->getValue());
	}
    
    public function testCreateWrongExpressionType()
    {
        $expression = new ItemSessionControl();
        
        $this->setExpectedException(
            '\\InvalidArgumentException',
            "The ExpressionEngine class only accepts QTI Data Model Expression objects to be processed."
        );
        
        $engine = new ExpressionEngine($expression);
    }
}
