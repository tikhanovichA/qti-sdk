<?php
namespace qtismtest\data;

use qtismtest\QtiSmTestCase;
use qtism\data\state\Weight;
use qtism\data\state\WeightCollection;

class QtiIdentifiableCollectionTest extends QtiSmTestCase {
	
	public function testWithWeights() {
		
		$weight1 = new Weight('weight1', 1.0);
		$weight2 = new Weight('weight2', 1.1);
		$weight3 = new Weight('weight3', 1.2);
		$weights = new WeightCollection(array($weight1, $weight2, $weight3));
		
		$this->assertTrue($weights['weight1'] === $weight1);
		$this->assertTrue($weights['weight2'] === $weight2);
		$this->assertTrue($weights['weight3'] === $weight3);
		
		$this->assertTrue($weights['weightX'] === null);
        $this->assertFalse(isset($weights['weightX']));
		
		// Can I address the by identifier?
		$this->assertTrue($weights['weight2'] === $weight2);
		
		// What happens if I change the identifier of an object.
		// Is it adressable with the new identifier?
		$weight2->setIdentifier('weightX');
		$this->assertTrue($weights['weightX'] === $weight2);
		$this->assertFalse(isset($weights['weight2']));
        $this->assertTrue(isset($weights['weightX']));
        
        // What happens if I remove an object?
        unset($weights['weightX']);
        $this->assertFalse(isset($weights['weightX']));
	}
    
    /**
     * @depends testWithWeights
     */
    public function testReplace()
    {
        $weight1 = new Weight('weight1', 1.0);
		$weight2 = new Weight('weight2', 1.1);
		$weight3 = new Weight('weight3', 1.2);
		$weights = new WeightCollection(array($weight1, $weight2, $weight3));
        
        // Let's replace weight2 with another Weight object having the same identifier.
        $this->assertSame($weight2, $weights['weight2']);
        
        $weightBis = new Weight('weight2', 2.0);
        $weights->replace($weight2, $weightBis);
        
        $this->assertFalse($weight2 === $weights['weight2']);
        $this->assertCount(3, $weights);
        
        // Is the order still respected?
        $this->assertSame(
            array('weight1', 'weight2', 'weight3'),
            $weights->getKeys()
        );
        
        // Let's replace (the new) weight2 with another Weight object having different identifiers.
        $weight4 = new Weight('weight4', 1.4);
        $weights->replace($weights['weight2'], $weight4);
        
        $this->assertCount(3, $weights);
        $this->assertFalse(isset($weights['weight2']));
        
        // Now check the order of things, let's get the keys and compare them.
        $this->assertSame(
            array('weight1', 'weight4', 'weight3'),
            $weights->getKeys()
        );
    }
    
    /**
     * @depends testReplace
     */
    public function testReplaceNotFound()
    {
        $weight1 = new Weight('weight1', 1.0);
        $weight2 = new Weight('weight2', 1.2);
        $weight3 = new Weight('weight3', 1.3);
        
		$weights = new WeightCollection(array($weight1, $weight2));
        
        $this->setExpectedException(
            '\\UnexpectedValueException',
            'The object you want to replace could not be found.'
        );
        $weights->replace($weight3, new Weight('weight4', 1.4));
    }
    
    /**
     * @depends testWithWeights
     */
    public function testEventsUnset()
    {
        $weight1 = new Weight('weight1', 1.0);
        $weight2 = new Weight('weight2', 1.2);
		$weights = new WeightCollection(array($weight1, $weight2));
        
        $this->assertCount(2, $weights);
        $this->assertTrue(isset($weights['weight1']));
        $this->assertTrue(isset($weights['weight2']));
        
        $weight1->setIdentifier('weightX');
        $this->assertCount(2, $weights);
        $this->assertFalse(isset($weights['weight1']));
        $this->assertTrue(isset($weights['weight2']));
        $this->assertTrue(isset($weights['weightX']));
        
        unset($weights['weightX']);
        $this->assertCount(1, $weights);
        $this->assertFalse(isset($weights['weightX']));
        $this->assertTrue(isset($weights['weight2']));
        
        $weight1->setIdentifier('weight2');
        $this->assertFalse($weight1 === $weights['weight2']);
    }
    
    public function testRenamingOrder()
    {
        $weight1 = new Weight('weight1', 1.0);
        $weight2 = new Weight('weight2', 1.2);
        $weight3 = new Weight('weight3', 1.2);
		$weights = new WeightCollection(array($weight1, $weight2, $weight3));
        
        // If weight2 gets a new identifier "weight4", it should still be in second position in the collection.
        $this->assertSame(
            array('weight1', 'weight2', 'weight3'),
            $weights->getKeys()
        );
        
        $weight2->setIdentifier('weight4');
        
        $this->assertSame(
            array('weight1', 'weight4', 'weight3'),
            $weights->getKeys()
        );
    }
    
    public function testOffsetGetNonString()
    {
        $this->setExpectedException(
            '\\OutOfRangeException',
            'The requested offset must be a non-empty string.'
        );
        
        $collection = new WeightCollection();
        $val = $collection[0];
    }
    
    public function testOffsetSetNonNull()
    {
        $this->setExpectedException(
            '\\OutOfRangeException',
            "No specific offset can be set in a QtiIdentifiableCollection. The offset is always infered from the 'identifier' attribute of the given QtiIdentifiable object. Given offset is 'offset'."
        );
        
        $collection = new WeightCollection();
        $val = $collection['offset'] = new Weight('weight1', 1.0);
    }
    
    public function testOffsetUnsetNonString()
    {
        $this->setExpectedException(
            '\\OutOfRangeException',
            'The requested offset must be a non-empty string.'
        );
        
        $collection = new WeightCollection();
        unset($collection[0]);
    }
    
    public function testClone()
    {
        $collection = new WeightCollection();
        $w01 = new Weight('W01', 1.0);
        $collection[] = $w01;
        
        $clone = clone $collection;
        $this->assertFalse($collection === $clone);
        $this->assertFalse($collection['W01'] === $clone['W01']);
        
        $clone['W01']->setIdentifier('W02');
        $this->assertFalse(isset($clone['W01']));
        $this->assertTrue(isset($clone['W02']));
        $this->assertEquals('W01', $w01->getIdentifier());
        $this->assertEquals('W01', $collection['W01']->getIdentifier());
        $this->assertFalse($w01 === $clone['W02']);
        
        $collection['W01']->setIdentifier('W03');
        $this->assertFalse(isset($collection['W01']));
        $this->assertTrue(isset($collection['W03']));
    }
}
