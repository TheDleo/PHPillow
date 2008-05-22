<?php
/**
 * Basic test cases for model connections
 *
 * @version $Revision$
 * @license GPLv3
 */

/**
 * Tests for the basic model
 */
class phpillowDocumentTests extends PHPUnit_Framework_TestCase
{
    /**
     * Return test suite
     *
     * @return PHPUnit_Framework_TestSuite
     */
	public static function suite()
	{
		return new PHPUnit_Framework_TestSuite( __CLASS__ );
	}

    public function setUp()
    {
        phpillowTestEnvironmentSetup::resetDatabase(
            array( 
                'database' => 'test',
            )
        );
    }

    public function testIdFromString()
    {
        // These tests do only work with a minimum glibc version of 2.7 and
        // depend on the installed locale. You may see different results, and
        // we include a set of possible valid results in the tests.
        if ( version_compare( ICONV_VERSION, '2.7', '<' ) )
        {
            $this->markTestSkipped( 'Minimum glibs version 2.7 required.' );
        }

        $document = new phpillowDocumentAllPublic();

        $this->assertSame(
            'kore',
            $document->stringToId( 'kore' )
        );

        $this->assertTrue(
            in_array( 
                $string = $document->stringToId( 'öäü' ),
                array( 
                    'oau', 
                    'oeaeue'
                )
            ),
            "String '$string' not in valid expectations."
        );

        $this->assertTrue(
            in_array( 
                $string = $document->stringToId( 'Žluťoučký kůň' ),
                array( 
                    'zlutoucky_kun', 
                )
            ),
            "String '$string' not in valid expectations."
        );

        $this->assertTrue(
            in_array( 
                $string = $document->stringToId( '!"§$%&/(=)Ä\'Ö*``\'"' ),
                array( 
                    '_a_o_',
                    '_ae_oe_'
                )
            ),
            "String '$string' not in valid expectations."
        );

        $this->assertTrue(
            in_array( 
                $string = $document->stringToId( '!"§$%&/(=)Ä\'Ö*``\'"', '-' ),
                array( 
                    '-a-o-', 
                    '-ae-oe-'
                )
            ),
            "String '$string' not in valid expectations."
        );
    }

    public function testCreateAndStoreUser()
    {
        $doc = phpillowUserDocument::createNew();

        try
        {
            $doc->save();
            $this->fail( 'Expected phpillowRuntimeException.' );
        }
        catch ( phpillowRuntimeException $e )
        { /* Expected exception */ }

        $doc->login = 'kore';
        $doc->save();
    }

    public function testFetchDocumentById()
    {
        $doc = phpillowUserDocument::createNew();
        $doc->login = 'kore';
        $doc->save();

        $doc = phpillowUserDocument::fetchById( 'user-kore' );

        $this->assertSame(
            'kore',
            $doc->login
        );

        $this->assertSame(
            null,
            $doc->name
        );
    }

    public function testDocumentFetchAndChange()
    {
        $doc = phpillowUserDocument::createNew();
        $doc->login = 'kore';
        $doc->save();

        $doc = phpillowUserDocument::fetchById( 'user-kore' );
        $doc->name = 'Kore (update)';
        $doc->save();

        $doc = phpillowUserDocument::fetchById( 'user-kore' );
        $doc->name = 'Kore (update)';

        $this->assertSame(
            'Kore (update)',
            $doc->name
        );
    }

    public function testDocumentFetchAndStoreUnmodified()
    {
        $doc = phpillowUserDocument::createNew();
        $doc->login = 'kore';
        $doc->save();

        $doc = phpillowUserDocument::fetchById( 'user-kore' );
        $this->assertFalse(
            $doc->save()
        );
    }

    public function testDocumentGetUnknownProperty()
    {
        $doc = phpillowUserDocument::createNew();

        try
        {
            $doc->unknown;
            $this->fail( 'Expected phpillowNoSuchPropertyException.' );
        }
        catch ( phpillowNoSuchPropertyException $e )
        { /* Expected exception */ }
    }

    public function testDocumentSetUnknownProperty()
    {
        $doc = phpillowUserDocument::createNew();

        try
        {
            $doc->unknown = 'foo';
            $this->fail( 'Expected phpillowNoSuchPropertyException.' );
        }
        catch ( phpillowNoSuchPropertyException $e )
        { /* Expected exception */ }
    }

    public function testDocumentFetchAndChangeRevisions()
    {
        $doc = phpillowUserDocument::createNew();
        $doc->login = 'kore';
        $doc->save();

        $doc = phpillowUserDocument::fetchById( 'user-kore' );
        $doc->login = 'Kore_2';
        $doc->name = 'Kore Nordmann';
        $doc->save();

        $doc = phpillowUserDocument::fetchById( 'user-kore' );
        $doc->name = 'Kore D. Nordmann';
        $doc->save();

        $doc = phpillowUserDocument::fetchById( 'user-kore' );

        $this->assertSame(
            'kore',
            $doc->revisions[0]->login
        );

        $this->assertSame(
            'Kore Nordmann',
            $doc->revisions[1]->name
        );

        $this->assertSame(
            'Kore D. Nordmann',
            $doc->revisions[2]->name
        );
    }
}