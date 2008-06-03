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
 * @version $Revision: 4 $
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */

/**
 * Response factory to create response objects from JSON results
 *
 * @package Core
 * @version $Revision: 4 $
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */
class phpillowResponse
{
    /**
     * Array containing all response properties
     * 
     * @var array
     */
    protected $properties;

    /**
     * Construct response object from JSON result
     * 
     * @param StdClass $body 
     * @return void
     */
    public function __construct( StdClass $body )
    {
        // Set all properties as virtual readonly repsonse object properties.
        foreach ( $body as $property => $value )
        {
            // All direct descandents, which are objects (StdClass) should be
            // transformed to arrays.
            if ( is_object( $value ) )
            {
                $value = (array) $value;
            }

            $this->properties[$property] = $value;
        }
    }

    /**
     * Get available property
     *
     * Receive response object property, if available. If the property is not
     * available, the method will throw an exception.
     * 
     * @param string $property 
     * @return mixed
     */
    public function __get( $property )
    {
        // Check if such an property exists at all
        if ( !isset( $this->properties[$property] ) )
        {
            throw new phpillowNoSuchPropertyException( $property );
        }

        return $this->properties[$property];
    }

    /**
     * Check if property exists.
     * 
     * Check if property exists.
     * 
     * @param string $property 
     * @return bool
     */
    public function __isset( $property )
    {
        return isset( $this->properties[$property] );
    }

    /**
     * Silently ignore each write access on response object properties.
     * 
     * @param string $property 
     * @param mixed $value 
     * @return bool
     */
    public function __set( $property, $value )
    {
        return false;
    }
}


