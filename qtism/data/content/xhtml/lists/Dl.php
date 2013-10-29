<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * Copyright (c) 2013 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 * @author Jérôme Bogaerts, <jerome@taotesting.com>
 * @license GPLv2
 * @package
 */

namespace qtism\data\content\xhtml\lists;

use qtism\data\content\FlowStatic;
use qtism\data\content\BlockStatic;
use qtism\data\content\BodyElement;
use \InvalidArgumentException;

/**
 * The dl XHTML class.
 * 
 * @author Jérôme Bogaerts <jerome@taotesting.com>
 *
 */
class Dl extends BodyElement implements BlockStatic, FlowStatic {
    
    /**
     * The base URI of the Ul.
     *
     * @var string
     * @qtism-bean-property
     */
    private $xmlBase = '';
    
    /**
     * The DlElement objects composing the Dl.
     * 
     * @var DlElementCollection
     * @qtism-bean-property
     */
    private $components;
    
    /**
     * Create a new Dl object.
     * 
     * @param string $id The id of the bodyElement.
     * @param string $class The class of the bodyElement.
     * @param string $lang The language of the bodyElement.
     * @param string $label The label of the bodyElement.
     * @throws InvalidArgumentException If one of the arguments is invalid.
     */
    public function __construct($id = '', $class = '', $lang = '', $label = '') {
        parent::__construct($id, $class, $lang, $label);
        $this->setComponents(new DlElementCollection());
    }
    
    /**
     * Set the DlElement objects composing the Dl.
     * 
     * @param DlElementCollection $components A collection of DlElement objects.
     */
    public function setComponents(DlElementCollection $components) {
        $this->components = $components;
    }
    
    /**
     * Get the DlElement objects composing the Dl.
     * 
     * @return DlElementCollection A collection of DlElement objects.
     */
    public function getComponents() {
        return $this->components;
    }
    
    public function getQtiClassName() {
        return 'dl';
    }
}