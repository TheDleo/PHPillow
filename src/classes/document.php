<?php
/**
 * phpillow CouchDB backend
 *
 * This file is part of phpillow.
 *
 * phpillow is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Lesser General Public License as published by the Free
 * Software Foundation; version 3 of the License.
 *
 * phpillow is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Lesser General Public License for
 * more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with phpillow; if not, write to the Free Software Foundation, Inc., 51
 * Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

/*
 * TODO:
 *
 * - Handle existing IDs
 * - Store editor and editing date in revisions
 * - Make storing old versions optional
 */

/**
 * Basic abstract document
 *
 * @package Core
 * @version $Revision$
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */
abstract class phpillowDocument
{
    /**
     * Object storing all the document properties as public attributes. This
     * way it is easy to serialize using json_encode.
     * 
     * @var StdClass
     */
    protected $storage;

    /**
     * Properties with they type and value validators
     *
     *  array(
     *      ...,
     *      email => new phpillowMailValidator( ... ),
     *      ...
     *  )
     * 
     * @var array
     */
    protected $properties = array();

    /**
     * List of required properties. For each required property, which is not
     * set, a validation exception will be thrown on save.
     * 
     * @var array
     */
    protected $requiredProperties = array();

    /**
     * Document type, may be a string matching the regular expression:
     *  (^[a-zA-Z0-9_]+$)
     * 
     * @var string
     */
    protected static $type = '_default';

    /**
     * Indicates wheather to keep old revisions of this document or not.
     *
     * @var bool
     */
    protected $versioned = true;

    /**
     * Flag, indicating if current document has already been modified
     * 
     * @var bool
     */
    protected $modified = false;

    /**
     * Flag, indicating if current document is a new one.
     * 
     * @var bool
     */
    protected $newDocument = true;

    /**
     * List of special properties, which are available beside the document
     * specific properties.
     *
     * @var array
     */
    protected static $specialProperties = array(
        '_id',
        '_rev',
        'type',
        'revisions',
    );

    /**
     * Construct new document
     * 
     * Construct new document
     * 
     * @return void
     */
    protected function __construct()
    {
        $this->storage = new StdClass();
        $this->storage->revisions = array();
        $this->storage->_id = null;

        // Set all defined properties to null on construct
        foreach ( $this->properties as $property => $v )
        {
            $this->storage->$property = null;
        }

        // Also store document type in document
        $this->storage->type = static::$type;
    }

    /**
     * Get document property
     * 
     * Get property from document
     *
     * @param string $property 
     * @return mixed
     */
    public function __get( $property )
    {
        // Check if property exists as a custom document property
        if ( isset( $this->properties[$property] ) )
        {
            return $this->storage->$property;
        }

        // Check if the requested property is one of the special properties,
        // which are available for all documents
        if ( in_array( $property, self::$specialProperties ) )
        {
            return $this->storage->$property;
        }

        // If none of the above checks passed, the request is invalid.
        throw new phpillowNoSuchPropertyException( $property );
    }

    /**
     * Set a property value
     *
     * Set a property value, which will be validated using the assigned
     * validator. Setting a property will mark the document as modified, so
     * that you know when to store the object.
     * 
     * @param string $property 
     * @param mixed $value 
     * @return void
     */
    public function __set( $property, $value )
    {
        // Check if property exists at all
        if ( !isset( $this->properties[$property] ) )
        {
            throw new phpillowNoSuchPropertyException( $property );
        }

        // Check if the passed value meets the property validation, and perform
        // nessecary transformation, like typecasts, or similar.
        //
        // If the value could not be fixed, this may throw an exception.
        $value = $this->properties[$property]->validate( $value );

        // Stotore value in storage object and mark document modified
        $this->storage->$property = $value;
        $this->modified = true;
    }

    /**
     * Set values from a response object
     *
     * Set values of the document from the response object, if they are
     * available in there.
     * 
     * @param phpillowResponse $response 
     * @return void
     */
    protected function fromResponse( phpillowResponse $response )
    {
        // Set all document property values from response, if available in the
        // response.
        //
        // Also fill a revision object with the set attributtes, so that the
        // current revision is also available in history, and it is stored,
        // when the object is modified and stored again.
        $revision = new StdClass();
        $revision->_date = time();
        foreach ( $this->properties as $property => $v ) 
        {
            if ( isset( $response->$property ) )
            {
                $this->storage->$property = $response->$property;
                $revision->$property = $response->$property;
            }
        }

        // Set special properties from response object
        $this->storage->_rev = $response->_rev;
        $this->storage->_id = $response->_id;

        // Check if the source document already contains a revision history and
        // store it in this case in the document object, if the object should
        // be versioned at all.
        if ( $this->versioned )
        {
            if ( isset( $response->revisions ) )
            {
                // @TODO: We may want tpo store old revisions as attachements
                // instead of polluting the normal document namespace.
                $this->storage->revisions = $response->revisions;
            }

            // Add current revision to revision history
            $this->storage->revisions[] = $revision;
        }

        // Document freshly loaded, so it is not modified, and not a new
        // document...
        $this->modified = false;
        $this->newDocument = false;
    }

    /**
     * Get document ID from object ID
     *
     * Composes the document ID out of the document type and the generated ID
     * for the current document.
     * 
     * @param string $type 
     * @param string $id 
     * @return string
     */
    protected static function getDocumentId( $type, $id )
    {
        return $type . '-' . $id;
    }

    /**
     * Get document by ID
     *
     * Get document by ID and return a document objetc instance for the fetch
     * document.
     * 
     * @param string $id 
     * @return phpillowDocument
     */
    public static function fetchById( $id )
    {
        // If a fetch is called with an empty ID, we throw an exception, as we
        // would get database statistics otherwise, and the following error may
        // be hard to debug.
        if ( empty( $id ) )
        {
            $error = new StdClass();
            $error->error  = 'not_found';
            $error->reason = 'No document ID specified.';
            throw new phpillowResponseNotFoundErrorException( $error );
        }

        // Fetch object from database
        $db = phpillowConnection::getInstance();
        $response = $db->get( 
            phpillowConnection::getDatabase() . urlencode( $id )
        );

        // Create document object fetched object
        $docType = get_called_class();
        $document = new $docType();
        $document->fromResponse( $response );

        return $document;
    }

    /**
     * Create a new document
     *
     * Create and initialize a new document
     * 
     * @return phpillowDocument
     */
    public static function createNew()
    {
        $docType = get_called_class();
        return new $docType();
    }

    /**
     * Get ID from document
     *
     * The ID normally should be calculated on some meaningful / unique
     * property for the current ttype of documents. The returned string should
     * not be too long and should not contain multibyte characters.
     * 
     * @return string
     */
    abstract protected function generateId();

    /**
     * Check if all requirements are met
     *
     * Checks if all required properties has been set. Returns an array with
     * the properties, whcih are required but not set, or true if all
     * requirements are fulfilled.
     * 
     * @return mixed
     */
    public function checkRequirements()
    {
        // Iterate over properties and check if they are set and not null
        $errors = array();
        foreach ( $this->requiredProperties as $property )
        {
            if ( !isset( $this->storage->$property ) ||
                 ( $this->storage->$property === null ) )
            {
                $errors[] = $property;
            }
        }

        // If error array is still empty all requirements are met
        if ( $errors === array() )
        {
            return true;
        }

        // Otherwise return the array with errors
        return $errors;
    }

    /**
     * Save the document
     *
     * If thew document has not been modfied the method will immediatly exit
     * and return false. If the document has been been modified, the modified
     * document will be stored in the database, keeping all the old revision
     * intact and return true on success.
     * 
     * @return bool
     */
    public function save()
    {
        // Ensure all requirements are checked, otherwise bail out with a
        // runtime exception.
        if ( $this->checkRequirements() !== true )
        {
            throw new phpillowRuntimeException(
                'Requirements not checked before storing the document.'
            );
        }

        // Check if we need to store the stuff at all
        if ( $this->modified === false )
        {
            return false;
        }

        // Generate a new ID, if this is a new document, otherwise reuse the
        // existing document ID.
        if ( $this->newDocument == true )
        {
            $this->storage->_id = static::getDocumentId( static::$type, $this->generateId() );
        }

        // Store document in database
        $db = phpillowConnection::getInstance();
        $db->put(
            phpillowConnection::getDatabase() . urlencode( $this->_id ),
            json_encode( $this->storage )
        );

        return true;
    }

    /**
     * Get ID string from arbritrary string
     *
     * To calculate an ID string from an phpillowrary string, first iconvs
     * tarnsliteration abilities are used, and after that all, but common ID
     * characters, are replaced by the given replace string, which defaults to
     * _.
     * 
     * @param string $string 
     * @param string $replace 
     * @return string
     */
    protected function stringToId( $string, $replace = '_' )
    {
        // First translit string to ASCII, as this characters are most probably
        // supported everywhere
        $string = iconv( 'UTF-8', 'ASCII//TRANSLIT', $string );

        // And then still replace any obscure characers by _ to ensure nothing
        // "bad" happens with this string.
        $string = preg_replace( '([^A-Za-z0-9.-]+)', $replace, $string );

        // Additionally we convert the string to lowercase, so that we get case
        // insensitive fetching
        return strtolower( $string );
    }
}
