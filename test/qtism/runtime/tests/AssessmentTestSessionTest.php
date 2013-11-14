<?php
require_once (dirname(__FILE__) . '/../../../QtiSmTestCase.php');

use qtism\runtime\tests\AssessmentItemSessionException;
use qtism\runtime\tests\AssessmentItemSessionState;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSessionFactory;
use qtism\runtime\common\State;
use qtism\data\NavigationMode;
use qtism\data\SubmissionMode;
use qtism\data\storage\xml\XmlCompactDocument;
use qtism\data\state\VariableDeclaration;
use qtism\data\state\OutcomeDeclarationCollection;
use qtism\runtime\common\VariableIdentifier;
use qtism\data\state\Weight;
use qtism\data\state\WeightCollection;
use qtism\data\AssessmentItemRef;
use qtism\data\AssessmentItemRefCollection;
use qtism\common\enums\Cardinality;
use qtism\common\enums\BaseType;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;
use qtism\runtime\tests\AssessmentTestSessionException;
use qtism\common\datatypes\Point;
use qtism\common\datatypes\DirectedPair;
use qtism\common\datatypes\Pair;
use qtism\runtime\common\MultipleContainer;
use qtism\common\datatypes\Duration;
use \OutOfBoundsException;

class AssessmentTestSessionTest extends QtiSmTestCase {
	
	protected $state;
	
	public function setUp() {
		parent::setUp();
		
		$xml = new XmlCompactDocument('1.0');
		$xml->load(self::samplesDir() . 'custom/runtime/assessmenttest_context.xml');
		
		$testSessionFactory = new AssessmentTestSessionFactory($xml->getDocumentComponent());
		$this->state = AssessmentTestSession::instantiate($testSessionFactory);
		$this->state['OUTCOME1'] = 'String!';
	}
	
	public function tearDown() {
	    parent::tearDown();
	    unset($this->state);
	}
	
	public function getState() {
		return $this->state;
	}
	
	public function testInstantiateOne() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $this->assertEquals(AssessmentTestSessionState::INITIAL, $assessmentTestSession->getState());
	    
	    // You cannot get information on the current elements of 
	    // the test session when INITIAL state is in force.
	    $this->assertFalse($assessmentTestSession->getCurrentAssessmentItemRef());
	    $this->assertFalse($assessmentTestSession->getCurrentAssessmentSection());
	    $this->assertFalse($assessmentTestSession->getCurrentNavigationMode());
	    $this->assertFalse($assessmentTestSession->getCurrentSubmissionMode());
	    $this->assertFalse($assessmentTestSession->getCurrentTestPart());
	    $this->assertFalse($assessmentTestSession->getCurrentRemainingAttempts());

	    $assessmentTestSession->beginTestSession();
	    $this->assertEquals(AssessmentTestSessionState::INTERACTING, $assessmentTestSession->getState());
	    
	    // Now that the test session has begun, you can get information
	    // about the current elements of the session.
	    $this->assertEquals('P01', $assessmentTestSession->getCurrentTestPart()->getIdentifier());
	    $this->assertEquals('S01', $assessmentTestSession->getCurrentAssessmentSection()->getIdentifier());
	    $this->assertEquals('Q01', $assessmentTestSession->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertInternalType('integer', $assessmentTestSession->getCurrentNavigationMode());
	    $this->assertEquals(NavigationMode::LINEAR, $assessmentTestSession->getCurrentNavigationMode());
	    $this->assertInternalType('integer', $assessmentTestSession->getCurrentSubmissionMode());
	    $this->assertEquals(SubmissionMode::INDIVIDUAL, $assessmentTestSession->getCurrentSubmissionMode());
	    $this->assertEquals(1, $assessmentTestSession->getCurrentRemainingAttempts());
	    
	    // all outcome variables should have their default value set.
	    // all response variables should be set to NULL.
	    foreach ($doc->getDocumentComponent()->getComponentsByClassName('assessmentItemRef') as $itemRef) {
	        $score = $assessmentTestSession[$itemRef->getIdentifier() . '.SCORE'];
	        $this->assertInternalType('float', $score);
	        $this->assertEquals(0.0, $score);
	        
	        $response = $assessmentTestSession[$itemRef->getIdentifier() . '.RESPONSE'];
	        $this->assertSame(null, $response);
	    }
	    
	    // test-level outcome variables should be initialized
	    // with their default values.
	    $this->assertInternalType('float', $assessmentTestSession['SCORE']);
	    $this->assertEquals(0.0, $assessmentTestSession['SCORE']);
	    
	    // No session ID should be set, this is the role of AssessmentTestSession Storage Services.
	    $this->assertEquals('no_session_id', $assessmentTestSession->getSessionId());
	}
	
	public function testInstantiateTwo() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection_withreplacement.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	    // check Q01.1, Q01.2, Q01.3 item session initialization.
	    for ($i = 1; $i <= 3; $i++) {
	        $score = $assessmentTestSession["Q01.${i}.SCORE"];
	        $response = $assessmentTestSession["Q01.${i}.RESPONSE"];
	        $this->assertInternalType('float', $score);
	        $this->assertEquals(0.0, $score);
	        $this->assertSame(null, $response);
	    }
	}
	
	public function testSetVariableValuesAfterInstantiationOne() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection.xml');
	     
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	    
	    // Change the value of the global SCORE.
	    $this->assertEquals(0.0, $assessmentTestSession['SCORE']);
	    $assessmentTestSession['SCORE'] = 20.0;
	    $this->assertEquals(20.0, $assessmentTestSession['SCORE']);
	    
	    // the assessment test session has no variable MAXSCORE.
	    $this->assertSame(null, $assessmentTestSession['MAXSCORE']);
	    try {
	        $assessmentTestSession['MAXSCORE'] = 20.0;
	        // An exception must be thrown in this case!
	        $this->assertTrue(false);
	    }
	    catch (OutOfBoundsException $e) {
	        $this->assertTrue(true);
	    }
	    
	    // Change the value of Q01.SCORE.
	    $this->assertEquals(0.0, $assessmentTestSession['Q01.SCORE']);
	    $assessmentTestSession['Q01.SCORE'] = 1.0;
	    $this->assertEquals(1.0, $assessmentTestSession['Q01.SCORE']);
	    
	    // Q01 has no 'MAXSCORE' variable.
	    $this->assertSame(null, $assessmentTestSession['Q01.MAXSCORE']);
	    try {
	        $assessmentTestSession['Q01.MAXSCORE'] = 1.0;
	        // An exception must be thrown !
	        $this->assertTrue(false);
	    }
	    catch (OutOfBoundsException $e) {
	        $this->assertTrue(true);
	    }
	    
	    // No item Q04.
	    $this->assertSame(null, $assessmentTestSession['Q04.SCORE']);
	    try {
	        $assessmentTestSession['Q04.SCORE'] = 1.0;
	        // Because no such item, outofbounds.
	        $this->assertTrue(false);
	    }
	    catch (OutOfBoundsException $e) {
	        $this->assertTrue(true);
	    }
	}
	
	public function testSetVariableValuesAfterInstantiationTwo() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection_withreplacement.xml');
	
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	     
	    // Change the value of Q01.2.SCORE.
	    $this->assertEquals(0.0, $assessmentTestSession['Q01.2.SCORE']);
	    $assessmentTestSession['Q01.2.SCORE'] = 1.0;
	    $this->assertEquals(1.0, $assessmentTestSession['Q01.2.SCORE']);
	    
	    // There is only 3 occurences of Q01. Try to go out of bounds.
	    try {
	        $assessmentTestSession['Q01.4.SCORE'] = 1.0;
	        // An OutOfBoundsException must be raised!
	        $this->assertTrue(false);  
	    }
	    catch (OutOfBoundsException $e) {
	        $this->assertTrue(true);
	    }
	}
	
	public function testLinearSkipAll() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	    
	    $this->assertEquals('Q01', $assessmentTestSession->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(0, $assessmentTestSession->getCurrentAssessmentItemRefOccurence());
	    $this->assertEquals('S01', $assessmentTestSession->getCurrentAssessmentSection()->getIdentifier());
	    $this->assertEquals('P01', $assessmentTestSession->getCurrentTestPart()->getIdentifier());
	    $this->assertFalse($assessmentTestSession->isCurrentAssessmentItemAdaptive());
	    
	    $assessmentTestSession->beginAttempt();
	    $assessmentTestSession->skip();
	    $this->assertEquals('Q02', $assessmentTestSession->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(0, $assessmentTestSession->getCurrentAssessmentItemRefOccurence());
	    $this->assertFalse($assessmentTestSession->isCurrentAssessmentItemAdaptive());
	    
	    $this->assertEquals(1, $assessmentTestSession->getCurrentRemainingAttempts());
	    $assessmentTestSession->beginAttempt();
	    $assessmentTestSession->skip();
	    $this->assertEquals('Q03', $assessmentTestSession->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(0, $assessmentTestSession->getCurrentAssessmentItemRefOccurence());
	    $this->assertFalse($assessmentTestSession->isCurrentAssessmentItemAdaptive());
	    
	    $assessmentTestSession->beginAttempt();
	    $assessmentTestSession->skip();
	    
	    $this->assertEquals(AssessmentTestSessionState::CLOSED, $assessmentTestSession->getState());
	    $this->assertFalse($assessmentTestSession->getCurrentAssessmentItemRef());
	    $this->assertFalse($assessmentTestSession->getCurrentAssessmentSection());
	    $this->assertFalse($assessmentTestSession->getCurrentTestPart());
	    $this->assertFalse($assessmentTestSession->getCurrentNavigationMode());
	    $this->assertFalse($assessmentTestSession->getCurrentSubmissionMode());
	}
	
	public function testLinearAnswerAll() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	    
	    // Q01 - Correct Response = 'ChoiceA'.
	    $this->assertEquals('Q01', $assessmentTestSession->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertFalse($assessmentTestSession->isCurrentAssessmentItemInteracting());
	    $assessmentTestSession->beginAttempt();
	    $this->assertTrue($assessmentTestSession->isCurrentAssessmentItemInteracting());
	    $responses = new State();
	    $responses->setVariable(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'));
	    $assessmentTestSession->endAttempt($responses);
	    $this->assertFalse($assessmentTestSession->isCurrentAssessmentItemInteracting());
	    
	    // Q02 - Correct Response = 'ChoiceB'.
	    $this->assertEquals('Q02', $assessmentTestSession->getCurrentAssessmentItemRef()->getIdentifier());
	    $assessmentTestSession->beginAttempt();
	    $responses = new State();
	    $responses->setVariable(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceC')); // -> incorrect x)
	    $assessmentTestSession->endAttempt($responses);
	    
	    // Q03 - Correct Response = 'ChoiceC'.
	    $this->assertEquals('Q03', $assessmentTestSession->getCurrentAssessmentItemRef()->getIdentifier());
	    $assessmentTestSession->beginAttempt();
	    $responses = new State();
	    $responses->setVariable(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceC'));
	    $assessmentTestSession->endAttempt($responses);
	    
	    // Check the final state of the test session.
	    // - Q01
	    $this->assertEquals('ChoiceA', $assessmentTestSession['Q01.RESPONSE']);
	    $this->assertInternalType('float', $assessmentTestSession['Q01.SCORE']);
	    $this->assertEquals(1.0, $assessmentTestSession['Q01.SCORE']);
	    $this->assertInternalType('integer', $assessmentTestSession['Q01.numAttempts']);
	    $this->assertEquals(1, $assessmentTestSession['Q01.numAttempts']);
	    
	    // - Q02
	    $this->assertEquals('ChoiceC', $assessmentTestSession['Q02.RESPONSE']);
	    $this->assertInternalType('float', $assessmentTestSession['Q02.SCORE']);
	    $this->assertEquals(0.0, $assessmentTestSession['Q02.SCORE']);
	    $this->assertInternalType('integer', $assessmentTestSession['Q02.numAttempts']);
	    $this->assertEquals(1, $assessmentTestSession['Q02.numAttempts']);
	    
	    // - Q03
	    $this->assertEquals('ChoiceC', $assessmentTestSession['Q03.RESPONSE']);
	    $this->assertInternalType('float', $assessmentTestSession['Q03.SCORE']);
	    $this->assertEquals(1.0, $assessmentTestSession['Q03.SCORE']);
	    $this->assertInternalType('integer', $assessmentTestSession['Q03.numAttempts']);
	    $this->assertEquals(1, $assessmentTestSession['Q03.numAttempts']);
	    
	    $this->assertEquals(AssessmentTestSessionState::CLOSED, $assessmentTestSession->getState());
	}
	
	public function testLinearSimultaneousSubmission() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/itemsubset_simultaneous.xml');
	    $this->assertTrue($doc->getDocumentComponent()->isExclusivelyLinear());
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    $session->beginTestSession();
	    
	    // Q01 - Correct.
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))));
	    
	    // !!! The Response must be stored in the session, but no score must be computed.
	    // This is the same for the next items.
	    $this->assertEquals('ChoiceA', $session['Q01.RESPONSE']);
	    $this->assertEquals(0.0, $session['Q01.scoring']);
	    $this->assertEquals(1, count($session->getPendingResponses()));
	    
	    // Q02 - Incorrect (but SCORE = 3)
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('A', 'P'), new Pair('C', 'M')))))));
	    $this->assertTrue($session['Q02.RESPONSE']->equals(new MultipleContainer(BaseType::PAIR, array(new Pair('A', 'P'), new Pair('C', 'M')))));
	    $this->assertEquals(0.0, $session['Q02.SCORE']);
	    $this->assertEquals(2, count($session->getPendingResponses()));
	    
	    // Q03 - Skip.
	    $session->beginAttempt();
	    $session->skip();
	    // When skipping, the pending responses consist of all response variable
	    // with their default value applied.
	    $this->assertEquals(3, count($session->getPendingResponses()));
	    
	    // Q04 - Skip.
	    $session->beginAttempt();
	    $session->skip();
	    $this->assertEquals(4, count($session->getPendingResponses()));
	    
	    // Q05 - Skip.
	    $session->beginAttempt();
	    $session->skip();
	    $this->assertEquals(5, count($session->getPendingResponses()));
	    
	    // Q06 - Skip.
	    $session->beginAttempt();
	    $session->skip();
	    $this->assertEquals(6, count($session->getPendingResponses()));
	    
	    // Q07.1 - Correct.
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(102, 113)))));
	    $this->assertTrue($session['Q07.1.RESPONSE']->equals(new Point(102, 113)));
	    $this->assertInternalType('float', $session['Q07.1.SCORE']);
	    $this->assertEquals(0.0, $session['Q07.1.SCORE']);
	    $this->assertEquals(7, count($session->getPendingResponses()));
	    
	    // Q07.2 - Incorrect (but SCORE = 1).
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(103, 113)))));
	    $this->assertTrue($session['Q07.2.RESPONSE']->equals(new Point(103, 113)));
	    $this->assertEquals(0.0, $session['Q07.2.SCORE']);
	    $this->assertEquals(8, count($session->getPendingResponses()));
	    
	    // Q07.3 - Incorrect (and SCORE = 0).
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(50, 60)))));
	    $this->assertTrue($session['Q07.3.RESPONSE']->equals(new Point(50, 60)));
	    $this->assertEquals(0.0, $session['Q07.3.SCORE']);
	    
	    // This is the end of the test. Then, the pending responses were flushed.
	    // We also have to check if the deffered response processing took place.
	    $this->assertEquals(0, count($session->getPendingResponses()));
	    
	    $this->assertEquals(1.0, $session['Q01.scoring']);
	    $this->assertEquals(3.0, $session['Q02.SCORE']);
	    $this->assertEquals(0.0, $session['Q03.SCORE']);
	    $this->assertEquals(0.0, $session['Q04.SCORE']);
	    $this->assertEquals(0.0, $session['Q05.SCORE']);
	    $this->assertEquals(0.0, $session['Q06.mySc0r3']);
	    
	    // Did the test-level outcome processing take place?
	    $this->assertEquals(9, $session['NPRESENTED']);
	}
	
	/**
	 * @dataProvider linearOutcomeProcessingProvider
	 * 
	 * @param array $responses
	 * @param array $outcomes
	 */
	public function testLinearOutcomeProcessing(array $responses, array $outcomes) {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/itemsubset.xml');
	     
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	    
	    // There must be 8 outcome variables to be checked:
	    // NCORRECTS01, NCORRECTS02, NCORRECTS03, NINCORRECT, NRESPONDED
	    // NPRESENTED, NSELECTED, PERCENT_CORRECT.
	    $this->assertEquals(array_keys($outcomes), array('NCORRECTS01', 'NCORRECTS02', 'NCORRECTS03', 'NINCORRECT', 'NRESPONSED', 'NPRESENTED', 'NSELECTED', 'PERCENT_CORRECT'));
	    
	    // The selection of items for the test is 9.
	    $this->assertEquals(9, count($responses));
	    
	    foreach ($responses as $resp) {
	        $assessmentTestSession->beginAttempt();
	        $assessmentTestSession->endAttempt($resp);
	    }
	    
	    $this->assertFalse($assessmentTestSession->isRunning());
	    $this->assertEquals(AssessmentTestSessionState::CLOSED, $assessmentTestSession->getState());
	    
	    foreach ($outcomes as $outcomeIdentifier => $outcomeValue) {
	        $this->assertInternalType((is_int($outcomeValue)) ? 'integer' : 'float', $assessmentTestSession[$outcomeIdentifier]);
	        
	        if ($outcomeIdentifier !== 'PERCENT_CORRECT') {
	            $this->assertEquals($outcomeValue, $assessmentTestSession[$outcomeIdentifier]);
	        }
	        else {
	            $this->assertEquals(round($outcomeValue, 2), round($assessmentTestSession[$outcomeIdentifier], 2));
	        }
	    }
	}
	
	public function linearOutcomeProcessingProvider() {
	    $returnValue = array();
	    
	    // Test 1.
	    $outcomes = array('NCORRECTS01' => 2, 'NCORRECTS02' => 1, 'NCORRECTS03' => 1, 'NINCORRECT' => 5, 'NRESPONSED' => 9, 'NPRESENTED' => 9, 'NSELECTED' => 9, 'PERCENT_CORRECT' => 44.44);
	    $responses = array();
	    $responses['Q01'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))); // SCORE = 1 - Correct
	    $responses['Q02'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('A', 'P'), new Pair('D', 'L')))))); // SCORE = 3 - Incorrect
	    $responses['Q03'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::IDENTIFIER, new MultipleContainer(BaseType::IDENTIFIER, array('H', 'O'))))); // SCORE = 2 - Correct
	    $responses['Q04'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::DIRECTED_PAIR, new MultipleContainer(BaseType::DIRECTED_PAIR, array(new DirectedPair('W', 'Sp'), new DirectedPair('G2', 'Su')))))); // SCORE = 0 - Incorrect
	    $responses['Q05'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('C', 'B'), new Pair('C', 'D'), new Pair('B', 'D')))))); // SCORE = 1 - Incorrect
	    $responses['Q06'] = new State(array(new ResponseVariable('answer', Cardinality::SINGLE, BaseType::IDENTIFIER, 'A'))); // SCORE = 1 - Correct
	    $responses['Q07.1'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(105, 105)))); // SCORE = 1 - Incorrect
	    $responses['Q07.2'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(102, 113)))); // SCORE = 1 - Correct
	    $responses['Q07.3'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(13, 37)))); // SCORE = 0 - Incorrect
	    
	    $test = array($responses, $outcomes);
	    $returnValue[] = $test;
	    
	    // Test 2 (full correct).
	    $outcomes = array('NCORRECTS01' => 3, 'NCORRECTS02' => 3, 'NCORRECTS03' => 3, 'NINCORRECT' => 0, 'NRESPONSED' => 9, 'NPRESENTED' => 9, 'NSELECTED' => 9, 'PERCENT_CORRECT' => 100.00);
	    $responses = array();
	    $responses['Q01'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))); // SCORE = 1 - Correct
	    $responses['Q02'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('A', 'P'), new Pair('C', 'M'), new Pair('D', 'L')))))); // SCORE = 4 - Correct
	    $responses['Q03'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::IDENTIFIER, new MultipleContainer(BaseType::IDENTIFIER, array('H', 'O'))))); // SCORE = 2 - Correct
	    $responses['Q04'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::DIRECTED_PAIR, new MultipleContainer(BaseType::DIRECTED_PAIR, array(new DirectedPair('W', 'G1'), new DirectedPair('Su', 'G2')))))); // SCORE = 3 - Correct
	    $responses['Q05'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('C', 'B'), new Pair('C', 'D')))))); // SCORE = 2 - Correct
	    $responses['Q06'] = new State(array(new ResponseVariable('answer', Cardinality::SINGLE, BaseType::IDENTIFIER, 'A'))); // SCORE = 1 - Correct
	    $responses['Q07.1'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(102, 113)))); // SCORE = 1 - Correct
	    $responses['Q07.2'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(102, 113)))); // SCORE = 1 - Correct
	    $responses['Q07.3'] = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(102, 113)))); // SCORE = 0 - Correct
	     
	    $test = array($responses, $outcomes);
	    $returnValue[] = $test;
	    
	    return $returnValue;
	}
	
	public function testWichLastOccurenceUpdate() {
		$doc = new XmlCompactDocument();
		$doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection_withreplacement.xml');
		
		$testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
		$assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
		$assessmentTestSession->beginTestSession();
		
		$this->assertFalse($assessmentTestSession->whichLastOccurenceUpdate($doc->getDocumentComponent()->getComponentByIdentifier('Q01')));
		
		$responses = new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA')));
		$assessmentTestSession->beginAttempt();
		$assessmentTestSession->endAttempt($responses);
		
		$this->assertEquals(0, $assessmentTestSession->whichLastOccurenceUpdate('Q01'));
		
		$assessmentTestSession->beginAttempt();
		$assessmentTestSession->skip();
		$this->assertEquals(0, $assessmentTestSession->whichLastOccurenceUpdate('Q01'));
		
		$assessmentTestSession->beginAttempt();
		$assessmentTestSession->endAttempt($responses);
		$this->assertEquals(2, $assessmentTestSession->whichLastOccurenceUpdate('Q01'));
	}
	
	public function testGetAssessmentItemSessions() {
	    // --- Test with single occurence items.
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	    
	    foreach (array('Q01', 'Q02', 'Q03') as $identifier) {
	        $sessions = $assessmentTestSession->getAssessmentItemSessions($identifier);
	        $this->assertEquals(1, count($sessions));
	        $this->assertEquals($identifier, $sessions[0]->getAssessmentItem()->getIdentifier());
	    }
	    
	    // Malformed $identifier.
	    try {
	        $sessions = $assessmentTestSession->getAssessmentItemSessions('Q04.1');
	        $this->assertFalse(true);
	    }
	    catch (InvalidArgumentException $e) {
	        $this->assertTrue(true);
	    }
	    
	    // Unknown assessmentItemRef.
	    $this->assertFalse($assessmentTestSession->getAssessmentItemSessions('Q04'));
	    
	    // --- Test with multiple occurence items.
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection_withreplacement.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $assessmentTestSession->beginTestSession();
	    
	    $sessions = $assessmentTestSession->getAssessmentItemSessions('Q01');
	    $this->assertEquals(3, count($sessions));
	    for ($i = 0; $i < count($sessions); $i++) {
	        $this->assertEquals('Q01', $sessions[$i]->getAssessmentItem()->getIdentifier());
	    }
	}
	
	public function testGetPreviousRouteItem() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection.xml');
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    $session->beginTestSession();
	    
	    // Try to get the previous route item but... there is no one because
	    // we are at the first item.
	    try {
	        $previousRouteItem = $session->getPreviousRouteItem();
	        
	        // An Exception should have been thrown.
	        $this->assertrue(false, 'An exception should have been thrown.');
	    }
	    catch (OutOfBoundsException $e) {
	        // Exception successfuly thrown.
	        $this->assertTrue(true);
	    }
	    
	    // Q01.
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q02.
	    $previousRouteItem = $session->getPreviousRouteItem();
	    $this->assertEquals('Q01', $previousRouteItem->getAssessmentItemRef()->getIdentifier());
	}
	
	public function testNextRouteItem() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/scenario_basic_nonadaptive_linear_singlesection.xml');
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    $session->beginTestSession();
	    
	    // Q01
	    $nextRouteItem = $session->getNextRouteItem();
	    $this->assertEquals('Q02', $nextRouteItem->getAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q02
	    $nextRouteItem = $session->getNextRouteItem();
	    $this->assertEquals('Q03', $nextRouteItem->getAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q03
	    // There is no more next route items.
	    try {
	        $nextRouteItem = $session->getNextRouteItem();
	        $this->assertTrue(false, 'An exception should have been thrown.');
	    }
	    catch (OutOfBoundsException $e) {
	        // Exception successfuly thrown dude!
	        $this->assertTrue(true);
	    }
	}
	
	public function testPossibleJumps() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/jumps.xml');
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    
	    // The session has not begun, the candidate is not able to jump anywhere.
	    $this->assertEquals(0, count($session->getPossibleJumps(false)));
	    
	    $session->beginTestSession();
	    $jumps = $session->getPossibleJumps(false);
	    $this->assertEquals(6, count($jumps));
	    $this->assertEquals('Q01', $jumps[0]->getAssessmentItemRef()->getIdentifier('Q01'));
	    $this->assertEquals(AssessmentItemSessionState::INITIAL, $jumps[0]->getItemSession()->getState());
	    $this->assertEquals('Q02', $jumps[1]->getAssessmentItemRef()->getIdentifier('Q02'));
	    $this->assertEquals(AssessmentItemSessionState::NOT_SELECTED, $jumps[1]->getItemSession()->getState());
	    $this->assertEquals('Q03', $jumps[2]->getAssessmentItemRef()->getIdentifier('Q03'));
	    $this->assertEquals('Q04', $jumps[3]->getAssessmentItemRef()->getIdentifier('Q04'));
	    $this->assertEquals('Q05', $jumps[4]->getAssessmentItemRef()->getIdentifier('Q05'));
	    $this->assertEquals('Q06', $jumps[5]->getAssessmentItemRef()->getIdentifier('Q06'));
	    
	    // The session has begun, the candidate is able to jump anywhere in testPart 'P01'.
	    for ($i = 0; $i < 6; $i++) {
	        $session->beginAttempt();
	        $session->skip();
	    }
	    
	    // We should be now in testPart 'PO2'.
	    $this->assertEquals('P02', $session->getCurrentTestPart()->getIdentifier());
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(0, $session->getCurrentAssessmentItemRefOccurence());
	    
	    $jumps = $session->getPossibleJumps(false);
	    $this->assertEquals(3, count($jumps));
	    $this->assertEquals('Q07', $jumps[0]->getAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(AssessmentItemSessionState::INITIAL, $jumps[0]->getItemSession()->getState());
	    $this->assertEquals(0, $jumps[0]->getOccurence());
	    $this->assertEquals('Q07', $jumps[1]->getAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(AssessmentItemSessionState::NOT_SELECTED, $jumps[1]->getItemSession()->getState());
	    $this->assertEquals(1, $jumps[1]->getOccurence());
	    $this->assertEquals('Q07', $jumps[2]->getAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(2, $jumps[2]->getOccurence());
	    
	    for ($i = 0; $i < 3; $i++) {
	        $session->beginAttempt();
	        $session->skip();
	    }
	    
	    // This is the end of the test session so no more possible jumps.
	    $this->assertEquals(0, count($session->getPossibleJumps(false)));
	}
	
	public function testJumps() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/jumps.xml');
	     
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	     
	    $session->beginTestSession();
	    
	    // Begin attempt at Q01.
	    $session->beginAttempt();
	    
	    // Moving to Q03 and answer it.
	    $session->jumpTo(2);
	    $this->assertEquals('Q03', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::IDENTIFIER, new MultipleContainer(BaseType::IDENTIFIER, array('H', 'O'))))));
	    $this->assertEquals(2.0, $session['Q03.SCORE']);
	    
	    // Come back at Q01.
	    $session->jumpTo(0);
	    $this->assertEquals('Q01', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))));
	    $this->assertEquals(1.0, $session['Q01.scoring']);
	    
	    // Autoforward enabled so we are at Q02.
	    $this->assertEquals('Q02', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('A', 'P')))))));
	    $this->assertEquals(2.0, $session['Q02.SCORE']);
	    
	    // Q03 Again because of autoforward.
	    $this->assertEquals('Q03', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    try {
	        $session->beginAttempt();
	        // Only a single attemp allowed.
	        $this->assertFalse(true, 'Only a single attempt is allowed for Q03.');
	    }
	    catch (AssessmentItemSessionException $e) {
	        $this->assertEquals(AssessmentItemSessionException::ATTEMPTS_OVERFLOW, $e->getCode());
	    }
	    
	    // Move to Q07.2
	    $session->jumpTo(7);
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(1, $session->getCurrentAssessmentItemRefOccurence());
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(102, 102)))));
	    $this->assertEquals(1.0, $session['Q07.2.SCORE']);
	    
	    // Q07.3
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(2, $session->getCurrentAssessmentItemRefOccurence());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // End of test, everything ok?
	    $this->assertInternalType('float', $session['Q01.scoring']);
	    $this->assertInternalType('float', $session['Q02.SCORE']);
	    $this->assertInternalType('float', $session['Q03.SCORE']);
	    $this->assertInternalType('float', $session['Q04.SCORE']); // Because auto forward = true, Q04 was selected as eligible after Q03's endAttempt. However, it was never attempted.
	    $this->assertSame(null, $session['Q05.SCORE']); // Was never selected.
	    $this->assertSame(null, $session['Q06.mySc0r3']); // Was never selected.
	    $this->assertSame(null, $session['Q07.1.SCORE']); // Was never selected.
	    $this->assertInternalType('float', $session['Q07.2.SCORE']);
	    $this->assertInternalType('float', $session['Q07.3.SCORE']);
	    
	    $this->assertEquals(5, $session['NPRESENTED']);
	    $this->assertEquals(6, $session['NSELECTED']);
	}
	
	public function testJumpsSimultaneous() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/jumps_simultaneous.xml');
	
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	
	    $session->beginTestSession();
	     
	    // Begin attempt at Q01.
	    $session->beginAttempt();
	     
	    // Moving to Q03 and answer it.
	    $session->jumpTo(2);
	    $this->assertEquals('Q03', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::IDENTIFIER, new MultipleContainer(BaseType::IDENTIFIER, array('H', 'O'))))));
	     
	    // Come back at Q01.
	    $session->jumpTo(0);
	    $this->assertEquals('Q01', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))));
	     
	    // Autoforward enabled so we are at Q02.
	    $this->assertEquals('Q02', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('A', 'P')))))));
	     
	    // Q03 Again because of autoforward.
	    $this->assertEquals('Q03', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    try {
	        $session->beginAttempt();
	        // Only a single attemp allowed.
	        $this->assertFalse(true, 'Only a single attempt is allowed for Q03.');
	    }
	    catch (AssessmentItemSessionException $e) {
	        $this->assertEquals(AssessmentItemSessionException::ATTEMPTS_OVERFLOW, $e->getCode());
	    }
	     
	    // Move to Q07.2. An error must be raised because the current test part is not finished and we are in SIMULTANEOUS MODE.
	    try {
	        $session->jumpTo(7);
	        $this->assertTrue(false, "The submission mode is SIMULTANEOUS but not all the testPart's items are responded. The deffered response processing cannot take place.");
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::FORBIDDEN_JUMP, $e->getCode());
	    }
	    
	    $this->assertEquals('Q03', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(0, $session->getCurrentAssessmentItemRefOccurence());
	    
	    // Go back in testPart P01 to complete it. Q04, Q05 and Q06 must be responsed.
	    $session->jumpTo(3);
	    // Q04
	    $this->assertEquals('Q04', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q05
	    $this->assertEquals('Q05', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q06
	    $this->assertEquals('Q06', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q07.1
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(0, $session->getCurrentAssessmentItemRefOccurence());
	    
	    // Jump to Q07.3
	    $session->jumpTo(8);
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(2, $session->getCurrentAssessmentItemRefOccurence());
	    
	    try {
	        $session->moveNext();
	        // Simultaneous mode in force but not all responses of P02 given.
	        $this->assertTrue(false);
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::MISSING_RESPONSES, $e->getCode());
	    }
	    
	    // Jump to Q07.1
	    $session->jumpTo(6);
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(0, $session->getCurrentAssessmentItemRefOccurence());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q07.2
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(1, $session->getCurrentAssessmentItemRefOccurence());
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q07.3 already answered.
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Outcome processing has now taken place. Everything OK?
	    $this->assertEquals(2.0, $session['Q03.SCORE']);
	    $this->assertEquals(2.0, $session['Q02.SCORE']);
	    $this->assertEquals(1.0, $session['Q01.scoring']);
	    $this->assertEquals(0.0, $session['Q04.SCORE']);
	    $this->assertEquals(0.0, $session['Q05.SCORE']);
	    $this->assertEquals(0.0, $session['Q06.mySc0r3']);
	    $this->assertEquals(0.0, $session['Q07.1.SCORE']);
	    $this->assertEquals(0.0, $session['Q07.2.SCORE']);
	    $this->assertEquals(0.0, $session['Q07.3.SCORE']);
	    
	    $this->assertEquals(9, $session['NSELECTED']);
	    $this->assertEquals(9, $session['NPRESENTED']);
	}
	
	public function testMoveBackLinear() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/itemsubset.xml');
	
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	
	    $session->beginTestSession();
	    $this->assertEquals(NavigationMode::LINEAR, $session->getCurrentNavigationMode());
	     
	    try {
	        $session->moveBack();
	        $this->assertTrue(false);
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::NAVIGATION_MODE_VIOLATION, $e->getCode());
	    }
	}
	
	public function testMoveNextAndBackNonLinearIndividual() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/itemsubset_nonlinear.xml');
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    
	    $session->beginTestSession();
	    $this->assertEquals(NavigationMode::NONLINEAR, $session->getCurrentNavigationMode());
	    $this->assertEquals(SubmissionMode::INDIVIDUAL, $session->getCurrentSubmissionMode());
	    
	    $this->assertEquals('Q01', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->moveNext();
	    $this->assertEquals('Q02', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->moveBack();
	    $this->assertEquals('Q01', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    
	    try {
	        // We are at the very first route item and want to move back... ouch!
	        $session->moveBack();
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::LOGIC_ERROR, $e->getCode());
	    }
	    
	    // We should still be on Q01.
	    $this->assertEquals('Q01', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $session->moveNext(); // Q02
	    $session->moveNext(); // Q03
	    $session->moveNext(); // Q04
	    $session->moveNext(); // Q05
	    $session->moveNext(); // Q06
	    $session->moveNext(); // Q07.1
	    $session->moveNext(); // Q07.2
	    $session->moveNext(); // Q07.3
	    
	    $this->assertEquals('Q07', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertEquals(2, $session->getCurrentAssessmentItemRefOccurence());
	    $session->moveNext();
	    
	    // OutcomeProcessing?
	    $this->assertInternalType('float', $session['PERCENT_CORRECT']);
	    $this->assertEquals(0.0, $session['PERCENT_CORRECT']);
	    $this->assertEquals(9, $session['NSELECTED']);
	}
	
	public function testMoveNextAndBackNonLinearSimultaneous() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/jumps_simultaneous.xml');
	     
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	     
	    $session->beginTestSession();
	    $this->assertEquals(NavigationMode::NONLINEAR, $session->getCurrentNavigationMode());
	    $this->assertEquals(SubmissionMode::SIMULTANEOUS, $session->getCurrentSubmissionMode());
	    
	    // Q01.
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))));
	    
	    // Q02.
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::PAIR, new MultipleContainer(BaseType::PAIR, array(new Pair('A', 'P')))))));
	    
	    // Q03.
	    $session->beginAttempt();
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::IDENTIFIER, new MultipleContainer(BaseType::IDENTIFIER, array('O'))))));
	    
	    // Q04.
	    $session->beginAttempt();
	    $session->skip();
	    
	    // Q05
	    $session->beginAttempt();
	    $session->skip();
	    
	    try {
	        // We are at the end of the testPart but Q06 not responsed. Cannot moveNext.
	        $session->moveNext();
	        $this->assertTrue(false);
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::MISSING_RESPONSES, $e->getCode());
	    }
	    
	    // Q06.
	    // (no scores computed yet).
	    $this->assertEquals(0.0, $session['Q01.scoring']);
	    $session->beginAttempt();
	    $session->skip();
	    
	    // We are now in another test part and some scores were processed for test part P01.
	    $this->assertEquals(1.0, $session['Q01.scoring']);
	}
	
	public function testTestPartAssessmentSectionsDurations() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/itemsubset.xml');
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    
	    // Try to get a duration on a non-begun test session.
	    $this->assertTrue($session['P01.duration']->equals(new Duration('PT0S')));
	    $this->assertTrue($session['S01.duration']->equals(new Duration('PT0S')));
	    
	    $session->beginTestSession();
	    $this->assertTrue($session['P01.duration']->equals(new Duration('PT0S')));
	    $this->assertTrue($session['S01.duration']->equals(new Duration('PT0S')));
	    
	    // Q01.
	    $session->beginAttempt();
	    sleep(1);
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))));
	    $this->assertTrue($session['P01.duration']->equals(new Duration('PT1S')));
	    $this->assertTrue($session['S01.duration']->equals(new Duration('PT1S')));
	    
	    // Q02.
	    $session->beginAttempt();
	    sleep(1);
	    $session->skip();
	    $this->assertTrue($session['P01.duration']->equals(new Duration('PT2S')));
	    $this->assertTrue($session['S01.duration']->equals(new Duration('PT2S')));
	    
	    // Try to get a duration that does not exist.
	    $this->assertSame(null, $session['P02.duration']);
	}
	
	public function testTestPartTimeLimitsLinear() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/timelimits_testparts_linear_individual.xml');
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    $session->beginTestSession();
	    
	    // Q01.
	    $session->beginAttempt();
	    sleep(2);
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceA'))));
	    $this->assertTrue($session->getRemainingTimeTestPart()->equals(new Duration('PT3S')));
	    
	    // Q02.
	    $session->beginAttempt();
	    sleep(2);
	    $session->updateDuration();
	    $this->assertTrue($session->getRemainingTimeTestPart()->equals(new Duration('PT1S')));
	    $session->skip();
	    
	    // Q03.
	    $session->beginAttempt();
	    sleep(2);
	    
	    try {
	        // P01.duration = 6 > maxTime -> exception !
	        $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::MULTIPLE, BaseType::IDENTIFIER, new MultipleContainer(BaseType::IDENTIFIER, array('H', 'O'))))));
	        $this->assertFalse(true);
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::TEST_PART_DURATION_OVERFLOW, $e->getCode());
	    }
	    
	    // We should have automatically be moved to the next test part.
	    $this->assertEquals('P02', $session->getCurrentTestPart()->getIdentifier());
	    $this->assertEquals('Q04', $session->getCurrentAssessmentItemRef()->getIdentifier());
	    $this->assertTrue($session->getRemainingTimeTestPart()->equals(new Duration('PT1S')));
	    
	    // Q04.
	    $session->beginAttempt();
	    sleep(2);
	    
	    try {
	        $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::POINT, new Point(102, 113)))));
	        $this->assertTrue(false);
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::TEST_PART_DURATION_OVERFLOW, $e->getCode());
	    }
	    
	    $this->assertEquals(AssessmentTestSessionState::CLOSED, $session->getState());
	    $this->assertFalse($session->getCurrentAssessmentItemRef());
	    
	    // Ok with outcome processing?
	    $this->assertEquals(1, $session['NRESPONSED']);
	}
	
	public function testUnlimitedAttempts() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/unlimited_attempts.xml');
	     
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    $session->beginTestSession();
	    $session->setAutoForward(false);
	    
	    $this->assertEquals(-1, $session->getCurrentRemainingAttempts());
	    $session->beginAttempt();
	    $this->assertEquals(-1, $session->getCurrentRemainingAttempts());
	    $session->skip();
	    $this->assertEquals(-1, $session->getCurrentRemainingAttempts());
	    
	    $session->beginAttempt();
	    $this->assertEquals(-1, $session->getCurrentRemainingAttempts());
	    $session->endAttempt(new State(array(new ResponseVariable('RESPONSE', Cardinality::SINGLE, BaseType::IDENTIFIER, 'ChoiceB'))));
	    $this->assertEquals(-1, $session->getCurrentRemainingAttempts());
	    
	    $session->moveNext();
	    $this->assertEquals(-1, $session->getCurrentRemainingAttempts());
	}
	
	public function testSuspendInteractItemSession() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/unlimited_attempts.xml');
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    
	    try {
	        // Try to suspend the item session on a not running test session...
	        $session->suspendItemSession();
	        $this->assertTrue(false, 'The item session cannot be suspended on a not running test session.');
	    }
	    catch (AssessmentTestSessionException $e) {
	        $this->assertEquals(AssessmentTestSessionException::STATE_VIOLATION, $e->getCode());
	    }
	    
	    $session->beginTestSession();
	    
	    try {
	        // Try to suspend an item session which is not yet begun.
	        $session->suspendItemSession();
	        $this->assertTrue(false, 'The item session cannot be suspended when it is in initial state.');
	    }
	    catch (AssessmentItemSessionException $e) {
	        $this->assertEquals(AssessmentItemSessionException::STATE_VIOLATION, $e->getCode());
	    }
	    
	    // Finally, suspend an item session in interacting state.
	    $this->assertEquals(AssessmentItemSessionState::INITIAL, $session->getCurrentAssessmentItemSession()->getState());
	    $session->beginAttempt();
	    $this->assertEquals(AssessmentItemSessionState::INTERACTING, $session->getCurrentAssessmentItemSession()->getState());
	    $session->suspendItemSession();
	    $this->assertEquals(AssessmentItemSessionState::SUSPENDED, $session->getCurrentAssessmentItemSession()->getState());
	    
	    // Try to re-enter interacting state.
	    $session->interactWithItemSession();
	    $this->assertEquals(AssessmentItemSessionState::INTERACTING, $session->getCurrentAssessmentItemSession()->getState());
	    
	    // Finally answer the question :) !
	    $responses = new State(array(new ResponseVariable('RESPONSE', BaseType::IDENTIFIER, Cardinality::SINGLE, 'ChoiceA')));
	    $session->endAttempt($responses);
	    $this->assertEquals(1.0, $session['Q01.scoring']);
	}
	
	/**
	 * @dataProvider getWeightProvider
	 * 
	 * @param string $identifier
	 * @param float $expectedValue
	 */
	public function testGetWeight($identifier, $expectedValue) {
		$state = $this->getState();
		
		$v = new VariableIdentifier($identifier);
		$weight = $state->getWeight($v);
		$this->assertInstanceOf('qtism\\data\\state\\Weight', $weight);
		$this->assertEquals($v->getVariableName(), $weight->getIdentifier());
		$this->assertEquals($expectedValue, $weight->getValue());
	}
	
	/**
	 * @dataProvider getWeightNotFoundProvider
	 * 
	 * @param string $identifier
	 */
	public function testGetWeightNotFound($identifier) {
		$state = $this->getState();
		
		$weight = $state->getWeight($identifier);
		$this->assertInternalType('boolean', $weight);
		$this->assertSame(false, $weight);
	}
	
	/**
	 * @dataProvider getWeightMalformed
	 * 
	 * @param string $identifier
	 */
	public function testGetWeightMalformed($identifier) {
	    $state = $this->getState();
	    $this->setExpectedException('\\InvalidArgumentException');
	    $state->getWeight($identifier);
	}
	
	public function getWeightProvider() {
		return array(
			array('Q01.W01', 1.0),
			array('Q01.W02', 1.1),
		    array('W01', 1.0),
		    array('W02', 1.1)
		);
	}
	
	public function getWeightNotFoundProvider() {
		return array(
			array('Q01.W03'),
			array('Q02.W02'),
		    array('Q01'),
		    array('W04')
		);
	}
	
	public function getWeightMalformed() {
	    return array(
	        array('_Q01'),
	        array('_Q01.SCORE'),
	        array('Q04.1.W01'),
	    );
	}
	
	public function testSelectionAndOrdering() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/selection_and_ordering_with_replacement.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $this->assertEquals(50, $assessmentTestSession->getRouteCount());
	}
	
	public function testOrderingBasic() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/ordering_basic.xml');

	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $this->assertEquals(3, $assessmentTestSession->getRouteCount());
	}
	
	public function testOrderingBasicFixed() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/ordering_basic_fixed.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $this->assertEquals(5, $assessmentTestSession->getRouteCount());
	}
    
	public function testOrderingVisible() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/ordering_visible.xml');
	     
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $this->assertEquals(9, $assessmentTestSession->getRouteCount());
	}
	
	public function testOrderingInvisibleDontKeepTogether() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/ordering_invisible_dont_keep_together.xml');
	
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $this->assertEquals(12, $assessmentTestSession->getRouteCount());
	}
	
	public function testOrderingInvisibleKeepTogether() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/ordering_invisible_keep_together.xml');
	
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    $this->assertEquals(12, $assessmentTestSession->getRouteCount());
	}
	
	public function testRouteItemAssessmentSections() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/routeitem_assessmentsections.xml');
	    
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	    
	    $route = $assessmentTestSession->getRoute();
	    
	    // Route[0] - S01 -> S01A -> Q01
	    $this->assertEquals('Q01', $route->getRouteItemAt(0)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(0)->getAssessmentSections();
	    $this->assertEquals(2, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S01']));
	    $this->assertTrue(isset($assessmentSections['S01A']));
	    // The returned assessment section must be the nearest parent section.
	    $this->assertEquals('S01A', $route->getRouteItemAt(0)->getAssessmentSection()->getIdentifier());
	    
	    // Route[1] - S01 -> S01A -> Q02
	    $this->assertEquals('Q02', $route->getRouteItemAt(1)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(1)->getAssessmentSections();
	    $this->assertEquals(2, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S01']));
	    $this->assertTrue(isset($assessmentSections['S01A']));
	    
	    // Check for the order (from to to bottom of the hierarchy)
	    $this->assertEquals(array('S01', 'S01A'), $assessmentSections->getKeys());
	    $this->assertEquals('S01A', $route->getRouteItemAt(1)->getAssessmentSection()->getIdentifier());
	    
	    // Route[2] - S01 -> S01A -> Q03
	    $this->assertEquals('Q03', $route->getRouteItemAt(2)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(2)->getAssessmentSections();
	    $this->assertEquals(2, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S01']));
	    $this->assertTrue(isset($assessmentSections['S01A']));
	    $this->assertEquals('S01A', $route->getRouteItemAt(0)->getAssessmentSection()->getIdentifier());
	    
	    // Route[3] - S01 -> S01B -> Q04
	    $this->assertEquals('Q04', $route->getRouteItemAt(3)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(3)->getAssessmentSections();
	    $this->assertEquals(2, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S01']));
	    $this->assertTrue(isset($assessmentSections['S01B']));
	    $this->assertEquals('S01B', $route->getRouteItemAt(3)->getAssessmentSection()->getIdentifier());
	    
	    // Route[4] - S01 -> S01B -> Q05
	    $this->assertEquals('Q05', $route->getRouteItemAt(4)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(4)->getAssessmentSections();
	    $this->assertEquals(2, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S01']));
	    $this->assertTrue(isset($assessmentSections['S01B']));
	    $this->assertEquals('S01B', $route->getRouteItemAt(4)->getAssessmentSection()->getIdentifier());
	    
	    // Route[5] - S01 -> S01B -> Q06
	    $this->assertEquals('Q06', $route->getRouteItemAt(5)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(5)->getAssessmentSections();
	    $this->assertEquals(2, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S01']));
	    $this->assertTrue(isset($assessmentSections['S01B']));
	    $this->assertEquals('S01B', $route->getRouteItemAt(5)->getAssessmentSection()->getIdentifier());
	    
	    // Route[6] - S02 -> Q07
	    $this->assertEquals('Q07', $route->getRouteItemAt(6)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(6)->getAssessmentSections();
	    $this->assertEquals(1, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S02']));
	    $this->assertEquals('S02', $route->getRouteItemAt(6)->getAssessmentSection()->getIdentifier());
	    
	    // Route[7] - S02 -> Q08
	    $this->assertEquals('Q08', $route->getRouteItemAt(7)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(7)->getAssessmentSections();
	    $this->assertEquals(1, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S02']));
	    $this->assertEquals('S02', $route->getRouteItemAt(7)->getAssessmentSection()->getIdentifier());
	    
	    // Route[8] - S02 -> Q09
	    $this->assertEquals('Q09', $route->getRouteItemAt(8)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(8)->getAssessmentSections();
	    $this->assertEquals(1, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S02']));
	    $this->assertEquals('S02', $route->getRouteItemAt(8)->getAssessmentSection()->getIdentifier());
	    
	    // Route[9] - S03 -> Q10
	    $this->assertEquals('Q10', $route->getRouteItemAt(9)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(9)->getAssessmentSections();
	    $this->assertEquals(1, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S03']));
	    $this->assertEquals('S03', $route->getRouteItemAt(9)->getAssessmentSection()->getIdentifier());
	    
	    // Route[10] - S03 -> Q11
	    $this->assertEquals('Q11', $route->getRouteItemAt(10)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(10)->getAssessmentSections();
	    $this->assertEquals(1, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S03']));
	    $this->assertEquals('S03', $route->getRouteItemAt(10)->getAssessmentSection()->getIdentifier());
	    
	    // Route[11] - S03 -> Q12
	    $this->assertEquals('Q12', $route->getRouteItemAt(11)->getAssessmentItemRef()->getIdentifier());
	    $assessmentSections = $route->getRouteItemAt(11)->getAssessmentSections();
	    $this->assertEquals(1, count($assessmentSections));
	    $this->assertTrue(isset($assessmentSections['S03']));
	    $this->assertEquals('S03', $route->getRouteItemAt(11)->getAssessmentSection()->getIdentifier());
	    
	    // Make sure that the assessmentSections are provided in the right order.
	    // For instance, the correct order for route[0] is [S01, S01A].
	    $order = array('S01', 'S01A');
	    $sections = $route->getRouteItemAt(0)->getAssessmentSections();
	    $this->assertEquals(count($order), count($sections));
	    $i = 0;
	    
	    $sections->rewind();
	    while ($sections->valid()) {
	        $current = $sections->current();
	        $this->assertEquals($order[$i], $current->getIdentifier());
	        $i++;
	        $sections->next();
	    }
	}
	
	public function testGetItemSessionControl() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/routeitem_itemsessioncontrols.xml');
	     
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	     
	    $route = $assessmentTestSession->getRoute();
	    
	    // Q01 - Must be under control of its own itemSessionControl.
	    $control = $route->getRouteItemAt(0)->getItemSessionControl();
	    $this->assertEquals(2, $control->getItemSessionControl()->getMaxAttempts());
	    $this->assertTrue($doc->getDocumentComponent()->getComponentByIdentifier('Q01') === $control->getOwner());
	    
	    // Q07 - Must be under control of the ItemSessionControl of the parent AssessmentSection.
	    $control = $route->getRouteItemAt(6)->getItemSessionControl();
	    $this->assertEquals(3, $control->getItemSessionControl()->getMaxAttempts());
	    $this->assertTrue($doc->getDocumentComponent()->getComponentByIdentifier('S02') === $control->getOwner());
	    
	    // Q10 - Is under no control.
	    $control = $route->getRouteItemAt(9)->getItemSessionControl();
	    $this->assertSame(null, $control);
	    
	    // Q13 - Must be under control of the ItemSessionControl of the parent TestPart.
	    $control = $route->getRouteItemAt(12)->getItemSessionControl();
	    $this->assertEquals(4, $control->getItemSessionControl()->getMaxAttempts());
	    $this->assertTrue($doc->getDocumentComponent()->getComponentByIdentifier('P02') === $control->getOwner());
	}
	
	public function testGetTimeLimits() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/routeitem_timelimits.xml');
	     
	    $testSessionFactory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $assessmentTestSession = AssessmentTestSession::instantiate($testSessionFactory);
	     
	    $route = $assessmentTestSession->getRoute();
	    
	    // Q01
	    $timeLimits = $route->getRouteItemAt(0)->getTimeLimits();
	    $this->assertEquals(3, count($timeLimits));
	    $this->assertEquals(600, $timeLimits[0]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(400, $timeLimits[1]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(50, $timeLimits[2]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    
	    // Q02
	    $timeLimits = $route->getRouteItemAt(1)->getTimeLimits();
	    $this->assertEquals(2, count($timeLimits));
	    $this->assertEquals(600, $timeLimits[0]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(400, $timeLimits[1]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    
	    // Q08
	    $timeLimits = $route->getRouteItemAt(7)->getTimeLimits();
	    $this->assertEquals(3, count($timeLimits));
	    $this->assertEquals(600, $timeLimits[0]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(400, $timeLimits[1]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(150, $timeLimits[2]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    
	    // Q12
	    $timeLimits = $route->getRouteItemAt(11)->getTimeLimits();
	    $this->assertEquals(2, count($timeLimits));
	    $this->assertEquals(600, $timeLimits[0]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(400, $timeLimits[1]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    
	    // Q13
	    $timeLimits = $route->getRouteItemAt(12)->getTimeLimits();
	    $this->assertEquals(2, count($timeLimits));
	    $this->assertEquals(600, $timeLimits[0]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(200, $timeLimits[1]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    
	    // Q14
	    $timeLimits = $route->getRouteItemAt(13)->getTimeLimits();
	    $this->assertEquals(1, count($timeLimits));
	    $this->assertEquals(600, $timeLimits[0]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    
	    
	    // Test item's timelimits exclusion.
	    // Q01
	    $timeLimits = $route->getRouteItemAt(0)->getTimeLimits(true);
	    $this->assertEquals(2, count($timeLimits));
	    $this->assertEquals(600, $timeLimits[0]->getTimeLimits()->getMaxTime()->getSeconds(true));
	    $this->assertEquals(400, $timeLimits[1]->getTimeLimits()->getMaxTime()->getSeconds(true));
	}
	
	public function testRubricBlockRefsHierarchy() {
	    $doc = new XmlCompactDocument();
	    $doc->load(self::samplesDir() . 'custom/runtime/rubricblockrefs_hierarchy.xml', true);
	    
	    $factory = new AssessmentTestSessionFactory($doc->getDocumentComponent());
	    $session = AssessmentTestSession::instantiate($factory);
	    $route = $session->getRoute();
	    
	    // S01 - S01A - Q01
	    $rubricBlockRefs = $route->getRouteItemAt(0)->getRubricBlockRefs();
	    $this->assertEquals(array('RB00_MAIN', 'RB01_MATH', 'RB02_MATH'), $rubricBlockRefs->getKeys());
	    
	    // S01 - S01A - Q02
	    $rubricBlockRefs = $route->getRouteItemAt(1)->getRubricBlockRefs();
	    $this->assertEquals(array('RB00_MAIN', 'RB01_MATH', 'RB02_MATH'), $rubricBlockRefs->getKeys());
	    
	    // S01 - S01B - Q03
	    $rubricBlockRefs = $route->getRouteItemAt(2)->getRubricBlockRefs();
	    $this->assertEquals(array('RB00_MAIN', 'RB03_BIOLOGY'), $rubricBlockRefs->getKeys());
	    
	    // S01C - Q04
	    $rubricBlockRefs = $route->getRouteItemAt(3)->getRubricBlockRefs();
	    $this->assertEquals(0, count($rubricBlockRefs));
	}
}