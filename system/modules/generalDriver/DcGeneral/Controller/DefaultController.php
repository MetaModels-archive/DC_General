<?php
/**
 * PHP version 5
 * @package    generalDriver
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Stefan Heimes <stefan_heimes@hotmail.com>
 * @author     Tristan Lins <tristan.lins@bit3.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace DcGeneral\Controller;

use DcGeneral\Controller\ControllerInterface;
use DcGeneral\Data\ConfigInterface;
use DcGeneral\Data\DCGE;
use DcGeneral\Data\ModelInterface;

use DcGeneral\DataContainerInterface;
use DcGeneral\Panel\DefaultPanel;

class DefaultController extends \Controller implements ControllerInterface
{
	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Vars
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	// Objects -----------------------

	/**
	 * Current DC General
	 *
	 * @var DataContainerInterface
	 */
	protected $objDC = null;

	/**
	 * Contao Encrypt class
	 * TODO: use dependency injection here.
	 * @var \Encryption
	 */
	protected $objEncrypt = null;

	// Current -----------------------

	/**
	 * A list with all current ID`s
	 * @var array
	 */
	protected $arrInsertIDs = array();

	// States ------------------------

	/**
	 * State of Show/Close all
	 * @var boolean
	 */
	protected $blnShowAllEntries = false;

	// Misc. -------------------------

	/**
	 * Error msg
	 *
	 * @var string
	 */
	protected $notImplMsg = "<div style='text-align:center; font-weight:bold; padding:40px;'>The function/view &quot;%s&quot; is not implemented.<br />Please <a target='_blank' style='text-decoration:underline' href='http://now.metamodel.me/en/sponsors/become-one#payment'>support us</a> to add this important feature!</div>";

	/**
	 * Field for the function sortCollection
	 *
	 * @var string $arrColSort
	 */
	protected $arrColSort;

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Magic functions
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	public function __construct()
	{
		parent::__construct();

		// Import
		// $this->import('Encryption');

		// Check some vars
		// FIXME: dependency injection
		$this->blnShowAllEntries = (\Input::getInstance()->get('ptg') == 'all') ? 1 : 0;
	}

	public function __call($name, $arguments)
	{
		switch ($name)
		{
			default:
				throw new \RuntimeException("Error Processing Request: " . $name, 1);
				break;
		}
	}

	public function instantiate($strClass)
	{
		return (in_array('getInstance', get_class_methods($strClass)))
			? call_user_func(array($strClass, 'getInstance'))
			: new $strClass();
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Getter & Setter
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Get DC General
	 * @return DataContainerInterface;

	 */
	public function getDC()
	{
		return $this->objDC;
	}

	/**
	 * Set DC General
	 * @param DataContainerInterface $objDC
	 */
	public function setDC($objDC)
	{
		$this->objDC = $objDC;
	}

	/**
	 * @return \DcGeneral\EnvironmentInterface
	 */
	protected function getEnvironment()
	{
		return $this->getDC()->getEnvironment();
	}

	/**
	 * Get filter for the data provider
	 *
	 * @todo Somtimes we don't need all filtersettings
	 * @todo add new var like level = all, root, parent etc.
	 * @todo check where we use this.
	 * @todo it`s a nice function, maybe a core function ?
	 *
	 * @return array();
	 */
	protected function getFilter()
	{
		$arrFilter = $this->getDC()->getFilter();

		if ($arrFilter)
		{
			return $arrFilter;
		}

		// Custom filter
		if (is_array($this->getDC()->arrDCA['list']['sorting']['filter']) && !empty($this->getDC()->arrDCA['list']['sorting']['filter']))
		{
			$arrFilters = array();
			foreach ($this->getDC()->arrDCA['list']['sorting']['filter'] as $filter)
			{
				$arrFilters[] = array('operation' => '=', 'property' => $filter[0], 'value' => $filter[1]);
			}
			if (count($arrFilters))
			{
				$this->getDC()->setFilter(array(array('operation' => 'AND', 'children' => $arrFilters)));
			}
		}

		if (is_array($this->getDC()->arrDCA['list']['sorting']['root']) && !empty($this->getDC()->arrDCA['list']['sorting']['root']))
		{
			$arrFilters = array();
			foreach ($this->getDC()->arrDCA['list']['sorting']['root'] as $mixId)
			{
				$arrFilters[] = array('operation' => '=', 'property' => 'id', 'value' => $mixId);
			}
			if (count($arrFilters))
			{
				$this->getDC()->setFilter(array(array('operation' => 'OR', 'children' => $arrFilters)));
			}
		}

		// TODO: we need to transport all the fields from the root conditions via the url and set filters accordingly here.
		// FIXME: this is only valid for mode 4 appearantly, fix for other views.
		// FIXME: dependency injection.
		if (\Input::getInstance()->get('table') && !is_null($this->getDC()->getParentTable()))
		{
			$objParentDP = $this->getDC()->getDataProvider('parent');
			$objParentItem = $objParentDP->fetch($objParentDP->getEmptyConfig()->setId(CURRENT_ID));
			$objCollection = $objParentDP->getEmptyCollection();
			// no parent item found, might have been deleted - we transparently create it for our filter to be able to filter to nothing.
			// TODO: shall we rather bail with "parent not found" than pushing all of this to the database?
			if (!$objParentItem)
			{
				$objParentItem = $objParentDP->getEmptyModel();
				$objParentItem->setID(CURRENT_ID);
			}
			$objCollection->add($objParentItem);
			// NOTE: we set the parent collection here, which will get used in the parentView() routine.
			$this->getDC()->setCurrentParentCollection($objCollection);
			$arrFilter = $this->getDC()->getChildCondition($objParentItem, 'self');
			$this->getDC()->setFilter($arrFilter);
		}

		// FIXME implement panel filter from session
		// FIXME all panels write into $this->getDC()->setFilter() or setLimit.

		return $this->getDC()->getFilter();
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 *  Core Support functions // Check Function
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Redirects to the real back end module.
	 */
	protected function redirectHome()
	{
		// FIXME: dependency injection.
		if (\Input::getInstance()->get('table') && \Input::getInstance()->get('id'))
		{
			$this->redirect(sprintf('contao/main.php?do=%s&table=%s&id=%s', \Input::getInstance()->get('do'), $this->getDC()->getTable(), \Input::getInstance()->get('id')));
		}

		$this->redirect('contao/main.php?do=' . \Input::getInstance()->get('do'));
	}

	/**
	 * Check if the curren model support multi language.
	 * Load the language from SESSION, POST or use a fallback.
	 *
	 * @return int return the mode multilanguage, singellanguage, see DCGE.php
	 */
	protected function checkLanguage()
	{
		// Load basic informations
		$intID = $this->getDC()->getId();
		$objDataProvider = $this->getDC()->getDataProvider();

		// Check if current dataprovider supports multilanguage
		if (in_array('DcGeneral\Data\MultiLanguageDriverInterface', class_implements($objDataProvider)))
		{
			$objLanguagesSupported = $this->getDC()->getDataProvider()->getLanguages($intID);
		}
		else if (in_array('InterfaceGeneralDataMultiLanguage', class_implements($objDataProvider)))
		{
			trigger_error('deprecated use of InterfaceGeneralDataMultiLanguage - use DcGeneral\Data\MultiLanguageDriverInterface instead.', E_USER_DEPRECATED);
			$objLanguagesSupported = $this->getDC()->getDataProvider()->getLanguages($intID);
		}
		else
		{
			$objLanguagesSupported = null;
		}

		//Check if we have some languages
		if ($objLanguagesSupported == null)
		{
			return DCGE::LANGUAGE_SL;
		}

		// Load language from Session
		$arrSession = $this->Session->get("dc_general");
		if (!is_array($arrSession))
		{
			$arrSession = array();
		}

		// try to get the language from session
		if (isset($arrSession["ml_support"][$this->getDC()->getTable()][$intID]))
		{
			$strCurrentLanguage = $arrSession["ml_support"][$this->getDC()->getTable()][$intID];
		}
		else
		{
			$strCurrentLanguage = $GLOBALS['TL_LANGUAGE'];
		}

		// Make a array from the collection
		$arrLanguage = array();
		foreach ($objLanguagesSupported as $value)
		{
			$arrLanguage[$value->getID()] = $value->getProperty("name");
		}

		// Get/Check the new language
		if (strlen($this->Input->post("language")) != 0 && $_POST['FORM_SUBMIT'] == 'language_switch')
		{
			if (key_exists($this->Input->post("language"), $arrLanguage))
			{
				$strCurrentLanguage = $this->Input->post("language");
				$arrSession["ml_support"][$this->getDC()->getTable()][$intID] = $strCurrentLanguage;
			}
			else if (key_exists($strCurrentLanguage, $arrLanguage))
			{
				$arrSession["ml_support"][$this->getDC()->getTable()][$intID] = $strCurrentLanguage;
			}
			else
			{
				$objlanguageFallback = $objDataProvider->getFallbackLanguage();
				$strCurrentLanguage = $objlanguageFallback->getID();
				$arrSession["ml_support"][$this->getDC()->getTable()][$intID] = $strCurrentLanguage;
			}
		}

		$this->Session->set("dc_general", $arrSession);

		$objDataProvider->setCurrentLanguage($strCurrentLanguage);

		return DCGE::LANGUAGE_ML;
	}

	/**
	 * Check if is editable AND not clodes
	 */
	protected function checkIsWritable()
	{
		// Check if table is editable
		if (!$this->getDC()->isEditable())
		{
			$this->log('Table ' . $this->getDC()->getTable() . ' is not editable', 'DC_General - DefaultController - copy()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// Check if table is editable
		if ((!$this->getDC()->getId()) && $this->getDC()->isClosed())
		{
			$this->log('Table ' . $this->getDC()->getTable() . ' is closed', 'DC_General - DefaultController - copy()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Clipboard functions
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Scan for children.
	 *
	 * This method is ready for mixed hierarchy and will return all children and grandchildren for the given table
	 * (or originating table of the model, if no provider name has been given) for all levels and parent child conditions.
	 *
	 * @param ModelInterface  $objModel        The model to assemble children from.
	 *
	 * @param string $strDataProvider The name of the data provider to fetch children from.
	 *
	 * @return array
	 */
	protected function assembleAllChildrenFromSame($objModel, $strDataProvider = '')
	{
		if ($strDataProvider == '')
		{
			$strDataProvider = $objModel->getProviderName();
		}

		$arrIds = array();

		if ($strDataProvider == $objModel->getProviderName())
		{
			$arrIds = array($objModel->getId());
		}

		// Check all data providers for children of the given element.
		foreach ($this->getEnvironment()->getDataDefinition()->getChildConditions($objModel->getProviderName()) as $objChildCondition)
		{
			$objDataProv = $this->getDC()->getDataProvider($objChildCondition->getDestinationName());
			$objConfig   = $objDataProv->getEmptyConfig();
			$objConfig->setFilter($objChildCondition->getFilter($objModel));

			foreach ($objDataProv->fetchAll($objConfig) as $objChild)
			{
				/** @var ModelInterface $objChild */
				if ($strDataProvider == $objChild->getProviderName())
				{
					$arrIds[] = $objChild->getId();
				}

				$arrIds = array_merge($arrIds, $this->assembleAllChildrenFromSame($objChild, $strDataProvider));
			}
		}

		return $arrIds;
	}

	/**
	 * Check clipboard state. Clear or save state of it.
	 */
	protected function checkClipboard()
	{
		$objInput     = $this->getEnvironment()->getInputProvider();
		$objClipboard = $this->getEnvironment()->getClipboard();

		// Reset Clipboard
		if ($objInput->getParameter('clipboard') == '1')
		{
			$objClipboard->clear();
			$this->redirectHome();
		}
		// Push some entry into clipboard.
		elseif ($objInput->getParameter('act') == 'paste')
		{
			$objDataProv  = $this->getDC()->getDataProvider();
			$id           = $objInput->getParameter('id');

			if ($objInput->getParameter('mode') == 'cut')
			{
				$arrIgnored = array($id);

				$objModel = $this->getDC()->getDataProvider()->fetch($objDataProv->getEmptyConfig()->setId($id));

				// We have to ignore all children of this element in mode 5 (to prevent circular references).
				if ($this->getEnvironment()->getDataDefinition()->getSortingMode() == 5)
				{
					$arrIgnored = $this->assembleAllChildrenFromSame($objModel);
				}

				$objClipboard
					->clear()
					->cut($id)
					->setCircularIds($arrIgnored);
			}
			else if ($objInput->getParameter('mode') == 'create')
			{
				// Get vars.
				$arrIgnored	 = array($id);
				$objModel	 = $this->getDC()->getDataProvider()->getEmptyModel();
				$objContainedId = trimsplit(',', $objInput->getParameter('childs'));

				// Set clipboard.
				$objClipboard
						->clear()
						->create($id)
						->setCircularIds($arrIgnored);

				// Set some more vars.
				if (is_array($objContainedId) && !empty($objContainedId))
				{
					$objClipboard->setContainedIds($objContainedId);
				}
			}
		}
		// Check clipboard from session.
		else
		{
			$objClipboard->loadFrom($this->getEnvironment());
		}

		// Let the clipboard save it's values persistent.
		$objClipboard->saveTo($this->getEnvironment());
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Core Functions
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Cut and paste
	 *
	 * <p>
	 * -= GET Parameter =-<br/>
	 * act		- Mode like cut | copy | and co <br/>
	 * <br/>
	 * after	- ID of target element to insert after <br/>
	 * into		- ID of parent element to insert into <br/>
	 * <br/>
	 * id		- Parent child ID used for redirect <br/>
	 * pid		- ID of the parent used in list mode 4,5 <br/>
	 * source	- ID of the element which should moved <br/>
	 * <br/>
	 * pdp		- Parent Data Provider real name <br/>
	 * cdp		- Current Data Provider real name <br/>
	 * <br/>
	 * -= Deprecated =-<br/>
	 * mode		- 1 Insert after | 2 Insert into (NEVER USED AGAIN - Deprecated) <br/>
	 * </p>
	 */
	public function cut()
	{
		// Checks
		$this->checkIsWritable();

		// Get vars
		$mixAfter  = \Input::getInstance()->get('after');
		$mixInto   = \Input::getInstance()->get('into');
		$intId     = \Input::getInstance()->get('id');
		$mixPid    = \Input::getInstance()->get('pid');
		$mixSource = \Input::getInstance()->get('source');
		$strPDP    = \Input::getInstance()->get('pdp');
		$strCDP    = \Input::getInstance()->get('cdp');

		// Deprecated
		$intMode  = \Input::getInstance()->get('mode');
		$mixChild = \Input::getInstance()->get('child');

		// Check basic vars
		if (empty($mixSource) || ( is_null($mixAfter) && is_null($mixInto) ) || empty($strCDP))
		{
			$this->log('Missing parameter for cut in ' . $this->getDC()->getTable(), __CLASS__ . ' - ' . __FUNCTION__, TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// Get current DataProvider
		if (!empty($strCDP))
		{
			$objCurrentDataProvider = $this->getDC()->getDataProvider($strCDP);
		}
		else
		{
			$objCurrentDataProvider = $this->getDC()->getDataProvider();
		}

		if ($objCurrentDataProvider == null)
		{
			throw new \RuntimeException('Could not load current data provider in ' . __CLASS__ . ' - ' . __FUNCTION__);
		}

		// Get parent DataProvider, if set
		$objParentDataProvider = null;
		if (!empty($strPDP))
		{
			$objParentDataProvider = $this->objDC->getDataProvider($strPDP);

			if ($objCurrentDataProvider == null)
			{
				throw new \RuntimeException('Could not load parent data provider ' . $strPDP . ' in ' . __CLASS__ . ' - ' . __FUNCTION__);
			}
		}

		// Load the source model
		$objSrcModel = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($mixSource));

		// Check mode
		switch ($this->getDC()->arrDCA['list']['sorting']['mode'])
		{
			case 0:
				$this->getNewPosition($objCurrentDataProvider, $objParentDataProvider, $objSrcModel, ($mixAfter == DCGE::INSERT_AFTER_START) ? 0 : $mixAfter, null, 'cut');
				break;
			case 1:
			case 2:
			case 3:
			case 4:
				$this->getNewPosition($objCurrentDataProvider, $objParentDataProvider, $objSrcModel, $mixAfter, $mixInto, 'cut');
				break;

			case 5:
				switch ($intMode)
				{
					case 1: // insert after
						// we want a new item in $strCDP having an optional parent in $strPDP (with pid item $mixPid) just after $mixAfter (in child tree conditions).
						// sadly, with our complex rules an getParent() is IMPOSSIBLE (or in other words way too costly as we would be forced to iterate through all items and check if this item would end up in their child collection).
						// therefore we get the child we want to be next of and set all fields to the same values as in the sibling to end up in the same parent.
						$objOtherChild = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($mixAfter));
						$this->getDC()->setSameParent($objSrcModel, $objOtherChild, $strCDP);

						// Update sorting.
						$this->getNewPosition($objCurrentDataProvider, $objParentDataProvider, $objSrcModel, $mixAfter, $mixInto, 'cut');
						break;

					case 2: // insert into
						// we want a new item in $strCDP having an optional parent in $strPDP (with pid item $mixPid) just as child of $mixAfter (in child tree conditions).
						// now check if we want to be inserted as root in our own condition - this means either no "after".
						if (($mixAfter == 0))
						{
							$this->setRoot($objSrcModel, 'self');
						}
						else
						{
							// enforce the child condition from our parent.
							$objMyParent = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($mixAfter));
							$this->setParent($objSrcModel, $objMyParent, 'self');
						}

						// Update sorting.
						$this->getNewPosition($objCurrentDataProvider, $objParentDataProvider, $objSrcModel, $mixAfter, $mixInto, 'cut');
						break;
					default:
						$this->log('Unknown create mode for copy in ' . $this->getDC()->getTable(), 'DC_General - DefaultController - copy()', TL_ERROR);
						$this->redirect('contao/main.php?act=error');
						break;
				}
				break;

			default:
				return vsprintf($this->notImplMsg, 'cut - Mode ' . $this->getDC()->arrDCA['list']['sorting']['mode']);
				break;
		}

		// Save new sorting
		$objCurrentDataProvider->save($objSrcModel);

		// Clear the clibboard.
		$this->getDC()->getEnvironment()->getClipboard()->clear();

		// If we have a ajax just stop here.
		if(\Environment::get('isAjaxRequest'))
		{
			exit();
		}
		// else return back to the start page.
		else
		{
			$this->redirectHome();
		}
	}

	public function move()
	{
		throw new \RuntimeException('HELP! move() is not implemented.');
	}

	/**
	 * Copy a entry and all childs
	 *
	 * @return string error msg for an unknown mode
	 */
	public function copy()
	{
		// Check
		$this->checkIsWritable();
		switch ($this->getDC()->arrDCA['list']['sorting']['mode'])
		{
			case 0:
			case 1:
			case 2:
			case 3:
				$intId = $this->Input->get('id');
				$intPid = (strlen($this->Input->get('pid')) != 0)? $this->Input->get('pid') : 0;

				if (strlen($intId) == 0)
				{
					$this->log('Missing parameter for copy in ' . $this->getDC()->getTable(), 'DC_General - DefaultController - copy()', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}

				// Check
				$this->checkIsWritable();
				$this->checkLanguage($this->getDC());

				// Load fields and co
				$this->getDC()->loadEditableFields();
				$this->getDC()->setWidgetID($this->getDC()->getId());

				// Check if we have fields
				if (!$this->getDC()->hasEditableFields())
				{
					$this->redirect($this->getReferer());
				}

				// Load something
				$this->getDC()->preloadTinyMce();

				// Load record from data provider - Load the source model
				$objDataProvider = $this->getDC()->getDataProvider();
				$objSrcModel = $objDataProvider->fetch($objDataProvider->getEmptyConfig()->setId($intId));

				$objDBModel = clone $objSrcModel;
				$objDBModel->setMeta(DCGE::MODEL_IS_CHANGED, true);

				$this->getDC()->setCurrentModel($objDBModel);

				// Check if we have a auto submit
				$this->getDC()->updateModelFromPOST();

				// Check submit
				if ($this->getDC()->isSubmitted() == true)
				{
					if (isset($_POST["save"]))
					{
						// process input and update changed properties.
						if ($this->doSave() !== false)
						{
							$this->reload();
						}
					}
					else if (isset($_POST["saveNclose"]))
					{
						// process input and update changed properties.
						if ($this->doSave() !== false)
						{
							setcookie('BE_PAGE_OFFSET', 0, 0, '/');

							$_SESSION['TL_INFO'] = '';
							$_SESSION['TL_ERROR'] = '';
							$_SESSION['TL_CONFIRM'] = '';

							$this->redirect($this->getReferer());
						}
					}
					// Maybe Callbacks ? Yes, this is the first version of an simple
					// button callback system like dc_memory.
					else
					{
						$arrButtons = $this->getDC()->arrDCA['buttons'];

						if (is_array($arrButtons))
						{
							foreach ($arrButtons as $arrButton)
							{
								if (empty($arrButton) || !is_array($arrButton))
								{
									continue;
								}

								if (array_key_exists($arrButton['formkey'], $_POST))
								{
									$strClass = $arrButton['button_callback'][0];
									$strMethod = $arrButton['button_callback'][1];

									$this->import($strClass);

									$this->$strClass->$strMethod($this->getDC());

									break;
								}
							}
						}

						if (Input::getInstance()->post('SUBMIT_TYPE') !== 'auto')
						{
							$this->reload();
						}
					}
				}

				return;

			case 5:
				// Init Vars
				$intMode = $this->Input->get('mode');
				$intPid = $this->Input->get('pid');
				$intId = $this->Input->get('id');
				$intChilds = $this->Input->get('childs');

				if (strlen($intMode) == 0 || strlen($intPid) == 0 || strlen($intId) == 0)
				{
					$this->log('Missing parameter for copy in ' . $this->getDC()->getTable(), 'DC_General - DefaultController - copy()', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}

				// Get the join field
				$arrJoinCondition = $this->getDC()->getJoinConditions('self');

				// Insert the copy
				$this->insertCopyModel($intId, $intPid, $intMode, $intChilds, $arrJoinCondition[0]['srcField'], $arrJoinCondition[0]['dstField'], $arrJoinCondition[0]['operation']);
				break;

			default:
				return vsprintf($this->notImplMsg, 'copy - Mode ' . $this->getDC()->arrDCA['list']['sorting']['mode']);
				break;
		}

		// Reset clipboard + redirect
		$this->resetClipboard(true);
	}

	/**
	 * Create a new entry
	 */
	public function create()
	{
		// Checks
		$this->checkIsWritable();
		$this->checkLanguage();

		// Load current values
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		// Load fields and co
		$this->getDC()->loadEditableFields();
		$this->getDC()->setWidgetID($this->getDC()->getId());

		// Check if we have fields
		if (!$this->getDC()->hasEditableFields())
		{
			$this->redirect($this->getReferer());
		}

		// Load something
		$this->getDC()->preloadTinyMce();

		// Load record from data provider
		$objDBModel = $objCurrentDataProvider->getEmptyModel();
		$this->getEnvironment()->setCurrentModel($objDBModel);

		if ($this->getDC()->arrDCA['list']['sorting']['mode'] < 4)
		{
			// check if the pid id/word is set
			if ($this->Input->get('pid'))
			{
				$objParentDP = $this->objDC->getDataProvider('parent');
				$objParent = $objParentDP->fetch($objParentDP->getEmptyConfig()->setId($this->Input->get('pid')));
				$this->setParent($objDBModel, $objParent, 'self');
			}
		}
		else if ($this->getDC()->arrDCA['list']['sorting']['mode'] == 4)
		{
			// check if the pid id/word is set
			if ($this->getEnvironment()->getInputProvider()->getParameter('pid') == '')
			{
				$this->log('Missing pid for new entry in ' . $this->getDC()->getTable(), 'DC_General - DefaultController - create()', TL_ERROR);
				$this->redirect('contao/main.php?act=error');
			}

			// FIXME: dependency injection.
			$objParentDP = $this->objDC->getDataProvider('parent');
			$objParent = $objParentDP->fetch($objParentDP->getEmptyConfig()->setId(\Input::getInstance()->get('pid')));
			$this->setParent($objDBModel, $objParent, 'self');
			$objCDP = $this->getDC()->getDataProvider();
			$arrChildCondition   = $this->objDC->getParentChildCondition($objParent, $objCDP->getEmptyModel()->getProviderName());
			$objDBModel->setProperty($arrChildCondition['setOn'][0]['to_field'], \Input::getInstance()->get('pid'));

		}
		// FIXME: dependency injection
		else if ($this->getDC()->arrDCA['list']['sorting']['mode'] == 5 && \Input::getInstance()->get('mode') != '')
		{
			/**
			 * Create in mode 5
			 *
			 * <p>
			 * -= GET Parameter =-<br/>
			 * act      - create <br/>
			 * after    - ID of target element <br/>
			 * mode     - 1 Insert after | 2 Insert into <br/>
			 * pid      - Id of the parent used in list mode 4,5 <br/>
			 * pdp      - Parent Data Provider real name <br/>
			 * cdp      - Current Data Provider real name <br/>
			 * id       - Parent child id used for redirect <br/>
			 * </p>
			 */
			// Get vars
			$mixAfter = \Input::getInstance()->get('after');
			$intMode = \Input::getInstance()->get('mode');
			$mixPid = \Input::getInstance()->get('pid');
			$strPDP = \Input::getInstance()->get('pdp');
			$strCDP = \Input::getInstance()->get('cdp');
			$intId = \Input::getInstance()->get('id');

			// Check basic vars
			if (is_null($mixAfter) || empty($intMode) || empty($strCDP))
			{
				$this->log('Missing parameter for create in ' . $this->getDC()->getTable(), __CLASS__ . ' - ' . __FUNCTION__, TL_ERROR);
				$this->redirect('contao/main.php?act=error');
			}

			// Load current data provider
			$objCurrentDataProvider = $this->objDC->getDataProvider($strCDP);
			if ($objCurrentDataProvider == null)
			{
				throw new \RuntimeException('Could not load current data provider in ' . __CLASS__ . ' - ' . __FUNCTION__);
			}

			$objParentDataProvider = null;
			if (!empty($strPDP))
			{
				$objParentDataProvider = $this->objDC->getDataProvider($strPDP);
				if ($objParentDataProvider == null)
				{
					throw new \RuntimeException('Could not load parent data provider ' . $strPDP . ' in ' . __CLASS__ . ' - ' . __FUNCTION__);
				}
			}

			// first enforce the parent table conditions, if we have an parent.
			if (($strPDP != $strCDP) && $mixPid)
			{
				// parenting entry is root? we want to become so too.
				if ($this->isRootEntry($strPDP, $mixPid))
				{
					$this->setRoot($objDBModel, $strPDP);
				}
				else
				{
					// we have some parent model and can use that one.
					$objParentModel = $objParentDataProvider->fetch($objParentDataProvider->getEmptyConfig()->setId($mixPid));
					$this->setParent($objDBModel, $objParentModel, $strPDP);
				}
				// TODO: update sorting here.
			}
			// FIXME: dependency injection.
			switch (\Input::getInstance()->get('mode'))
			{
				case 1: // insert after
					// we want a new item in $strCDP having an optional parent in $strPDP (with pid item $mixPid) just after $mixAfter (in child tree conditions).
					// sadly, with our complex rules an getParent() is IMPOSSIBLE (or in other words way too costly as we would be forced to iterate through all items and check if this item would end up in their child collection).
					// therefore we get the child we want to be next of and set all fields to the same values as in the sibling to end up in the same parent.
					$objOtherChild = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($mixAfter));
					if (!$this->getEnvironment()->getDataDefinition($strCDP)->getRootCondition()->matches($objOtherChild))
					{
						$this->getDC()->setSameParent($objDBModel, $objOtherChild, $strCDP);
					}
					else
					{
						// Enforce the child condition from our parent.
						$objSibling = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($mixAfter));
						$intParendId = $objSibling->getProperty('pid');
						
						// If we have a parent use it else set as root entry.
						if(!empty($intParendId))
						{
							$objParent = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($intParendId));
							$this->setParent($objDBModel, $objParent, 'self');
						}
						else
						{
							$this->setRoot($objDBModel, 'self');
						}
					}
					// TODO: update sorting here.
					break;

				case 2: // insert into
					// we want a new item in $strCDP having an optional parent in $strPDP (with pid item $mixPid) just as child of $mixAfter (in child tree conditions).
					// now check if we want to be inserted as root in our own condition - this means either no "after".
					if (($mixAfter == 0))
					{
						$this->setRoot($objDBModel, 'self');
					}
					else
					{
						// enforce the child condition from our parent.
						$objMyParent = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($mixAfter));
						$this->setParent($objDBModel, $objMyParent, 'self');
					}
					// TODO: update sorting here.
					break;

				default:
					$this->log('Unknown create mode for new entry in ' . $this->getDC()->getTable(), 'DC_General - DefaultController - create()', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
					break;
			}

			// Reset clipboard
			$this->getEnvironment()
					->getClipboard()
					->clear()
					->saveTo($this->getEnvironment());
		}
		try
		{
			// Check if we have a auto submit
			$this->getDC()->updateModelFromPOST();

			// Check submit
			if ($this->getDC()->isSubmitted() == true && !$this->getDC()->isNoReload())
			{
				try
				{
					// Get new Position
					$strPDP   = \Input::getInstance()->get('pdp');
					$strCDP   = \Input::getInstance()->get('cdp');
					$mixAfter = \Input::getInstance()->get('after');
					$mixInto  = \Input::getInstance()->get('into');

					$this->getNewPosition(
						$this->objDC->getDataProvider($strPDP),
						$this->objDC->getDataProvider($strCDP),
						$this->getEnvironment()->getCurrentModel(),
						$mixAfter,
						$mixInto,
						'create'
					);

					if (isset($_POST["save"]))
					{
						// process input and update changed properties.
						if (($objModell = $this->doSave()) !== false)
						{
							// Callback
							$this->getDC()->getCallbackClass()->oncreateCallback($objDBModel->getID(), $objDBModel->getPropertiesAsArray());
							// Log
							$this->log('A new entry in table "' . $this->getDC()->getTable() . '" has been created (ID: ' . $objModell->getID() . ')', 'DC_General - DefaultController - create()', TL_GENERAL);
							// Redirect
							$this->redirect($this->addToUrl("id=" . $objDBModel->getID() . "&amp;act=edit"));
						}
					}
					else if (isset($_POST["saveNclose"]))
					{
						// process input and update changed properties.
						if (($objModell = $this->doSave()) !== false)
						{
							setcookie('BE_PAGE_OFFSET', 0, 0, '/');

							$_SESSION['TL_INFO'] = '';
							$_SESSION['TL_ERROR'] = '';
							$_SESSION['TL_CONFIRM'] = '';

							// Callback
							$this->getDC()->getCallbackClass()->oncreateCallback($objDBModel->getID(), $objDBModel->getPropertiesAsArray());
							// Log
							$this->log('A new entry in table "' . $this->getDC()->getTable() . '" has been created (ID: ' . $objModell->getID() . ')', 'DC_General - DefaultController - create()', TL_GENERAL);
							// Redirect
							$this->redirect($this->getReferer());
						}
					}
					else
					{
						$arrButtons = $this->getDC()->arrDCA['buttons'];

						if (is_array($arrButtons))
						{
							foreach ($arrButtons as $arrButton)
							{
								if (empty($arrButton) || !is_array($arrButton))
								{
									continue;
								}

								if (array_key_exists($arrButton['formkey'], $_POST))
								{
									$strClass	 = $arrButton['button_callback'][0];
									$strMethod	 = $arrButton['button_callback'][1];

									$this->import($strClass);

									$this->$strClass->$strMethod($this->getDC());

									break;
								}
							}
						}
					}
				}
				catch (\Exception $exc)
				{
					$_SESSION['TL_ERROR'][] = sprintf('Exception: %s in file %s on line %s', $exc->getMessage(), $exc->getFile(), $exc->getLine());
				}
			}
		}
		catch (Exception $exc)
		{
			$_SESSION['TL_ERROR'][] = $exc->getMessage();
		}
	}

	/**
	 * Recurse through all childs in mode 5 and return their Ids.
	 */
	protected function fetchMode5ChildsOf($objParentModel, $blnRecurse = true)
	{
		$arrJoinCondition = $this->getDC()->getChildCondition($objParentModel, 'self');

		// Build filter
		$objChildConfig = $this->getDC()->getDataProvider()->getEmptyConfig();
		$objChildConfig->setFilter($arrJoinCondition);

		// Get child collection
		$objChildCollection = $this->getDC()->getDataProvider()->fetchAll($objChildConfig);

		$arrIDs = array();
		foreach ($objChildCollection as $objChildModel)
		{
			$arrIDs[] = $objChildModel->getID();
			if ($blnRecurse)
			{
				$arrIDs = array_merge($arrIDs, $this->fetchMode5ChildsOf($objChildModel, $blnRecurse));
			}
		}
		return $arrIDs;
	}

	public function delete()
	{
		// Load current values
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		// Init some vars
		$intRecordID = $this->getDC()->getId();

		// Check if we have a id
		if (strlen($intRecordID) == 0)
		{
			$this->redirectHome();
		}

		// Check if is it allowed to delete a record
		if ($this->getDC()->arrDCA['config']['notDeletable'])
		{
			$this->log('Table "' . $this->getDC()->getTable() . '" is not deletable', 'DC_General - DefaultController - delete()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// Callback
		$this->getEnvironment()->setCurrentModel($objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($intRecordID)));

		if($this->getEnvironment()->getCurrentModel() == null)
		{
			$this->redirectHome();
		}

		$this->getEnvironment()->getCallbackHandler()->ondeleteCallback();

		$arrDelIDs = array();

		// Delete record
		switch ($this->getDC()->arrDCA['list']['sorting']['mode'])
		{
			case 0:
			case 1:
			case 2:
			case 3:
			case 4:
				$arrDelIDs = array();
				$arrDelIDs[] = $intRecordID;
				break;

			case 5:
				$arrDelIDs = $this->fetchMode5ChildsOf($this->getDC()->getEnvironment()->getCurrentModel(), $blnRecurse = true);
				$arrDelIDs[] = $intRecordID;
				break;
		}

		// Delete all entries
		foreach ($arrDelIDs as $intId)
		{
			$this->getDC()->getDataProvider()->delete($intId);

			// Add a log entry unless we are deleting from tl_log itself
			if ($this->getDC()->getTable() != 'tl_log')
			{
				$this->log('DELETE FROM ' . $this->getDC()->getTable() . ' WHERE id=' . $intId, 'DC_General - DefaultController - delete()', TL_GENERAL);
			}
		}

		$this->redirect($this->getReferer());
	}

	public function edit()
	{
		// Load some vars
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		// Check
		$this->checkIsWritable();
		$this->checkLanguage($this->getDC());

		// Load an older Version
		// TODO: dependency injection.
		if (strlen(\Input::getInstance()->post("version")) != 0 && $this->getDC()->isVersionSubmit())
		{
			// TODO: dependency injection.
			$this->loadVersion($this->getDC()->getId(), \Input::getInstance()->post("version"));
		}

		// Load fields and co
		$this->getDC()->loadEditableFields();
		$this->getDC()->setWidgetID($this->getDC()->getId());

		// Check if we have fields
		if (!$this->getDC()->hasEditableFields())
		{
			$this->redirect($this->getReferer());
		}

		// Load something
		$this->getDC()->preloadTinyMce();

		// Load record from data provider
		$objDBModel = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($this->getDC()->getId()));
		if ($objDBModel == null)
		{
			$objDBModel = $objCurrentDataProvider->getEmptyModel();
		}

		$this->getEnvironment()->setCurrentModel($objDBModel);

		// Check if we have a auto submit
		$this->getDC()->updateModelFromPOST();

		// Check submit
		if ($this->getDC()->isSubmitted() == true)
		{
			if (isset($_POST["save"]))
			{
				// process input and update changed properties.
				if ($this->doSave() !== false)
				{
					$this->reload();
				}
			}
			else if (isset($_POST["saveNclose"]))
			{
				// process input and update changed properties.
				if ($this->doSave() !== false)
				{
					setcookie('BE_PAGE_OFFSET', 0, 0, '/');

					$_SESSION['TL_INFO'] = '';
					$_SESSION['TL_ERROR'] = '';
					$_SESSION['TL_CONFIRM'] = '';

					$this->redirect($this->getReferer());
				}
			}
			// Maybe Callbacks ? Yes, this is the first version of an simple
			// button callback system like dc_memory.
			else
			{
				$arrButtons = $this->getDC()->arrDCA['buttons'];

				if (is_array($arrButtons))
				{
					foreach ($arrButtons as $arrButton)
					{
						if (empty($arrButton) || !is_array($arrButton))
						{
							continue;
						}

						if (key_exists($arrButton['formkey'], $_POST))
						{
							$strClass	 = $arrButton['button_callback'][0];
							$strMethod	 = $arrButton['button_callback'][1];

							$this->import($strClass);

							$this->$strClass->$strMethod($this->getDC());

							break;
						}
					}
				}

//				// FIXME: dependency injection.
//				if (\Input::getInstance()->post('SUBMIT_TYPE') !== 'auto')
//				{
//					$this->reload();
//				}
			}
		}
	}

	/**
	 * Show informations about one entry
	 */
	public function show()
	{
		// Load check multi language
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		// Check
		$this->checkLanguage($this->getDC());

		// Load record from data provider
		$objDBModel = $objCurrentDataProvider->fetch($objCurrentDataProvider->getEmptyConfig()->setId($this->getDC()->getId()));

		if ($objDBModel == null)
		{
			$this->log('Could not find ID ' . $this->getDC()->getId() . ' in Table ' . $this->getDC()->getTable() . '.', 'DC_General show()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$this->getDC()->getEnvironment()->setCurrentModel($objDBModel);
	}

	protected function buildPanel()
	{
		$objContainer = new \DcGeneral\Panel\DefaultPanelContainer();
		$objContainer->setDataContainer($this->getDC());

		$objContainer->buildFrom($this->getDC()->getDataDefinition());

		$objGlobalConfig = $this->getDC()->getDataProvider()->getEmptyConfig();
		$objContainer->initialize($objGlobalConfig);

		$this->getDC()->getEnvironment()->setPanelContainer($objContainer);

		return $objContainer;
	}

	/**
	 * Show all entries from a table
	 *
	 * @return void | String if error
	 */
	public function showAll()
	{
		// TODO: temporarily hardcoded here - panel should be build somewhere else like show.
		$this->buildPanel();

		// Checks
		$this->checkClipboard();
		$this->checkPanelSubmit();

		// Setup
		$this->getDC()->setButtonId('tl_buttons');
		$this->getFilter();
		$this->generatePanelFilter('set');

		// Set the first sorting.
		$objCurrentDataProvider	 = $this->getDC()->getDataProvider();
		$objConfig				 = $objCurrentDataProvider->getEmptyConfig();
		$this->getDC()->getEnvironment()->getPanelContainer()->initialize($objConfig);
		$arrSorting				 = $objConfig->getSorting();

		if (count($arrSorting))
		{
			foreach ($arrSorting as $strField => $strOrder)
			{
				$this->getDC()->setFirstSorting($strField, $strOrder);
				break;
			}
		}
		// Set default values from DCA
		else
		{
			$arrSorting		 = (array) $this->getDC()->arrDCA['list']['sorting']['fields'];
			$strFirstSorting = preg_replace('/\s+.*$/i', '', strval($arrSorting[0]));

			if (!isset($this->getDC()->arrDCA['list']['sorting']['flag']))
			{
				$strFirstSortingOrder = DCGE::MODEL_SORTING_ASC;
			}
			else
			{
				$strFirstSortingOrder = $this->getDC()->arrDCA['list']['sorting']['flag'] % 2 ? DCGE::MODEL_SORTING_ASC : DCGE::MODEL_SORTING_DESC;
			}

			if (!strlen($strFirstSorting))
			{
				foreach (array('sorting', 'tstamp', 'pid', 'id') as $strField)
				{
					if ($objCurrentDataProvider->fieldExists($strField))
					{
						$strFirstSorting = $strField;
						break;
					}
				}
			}

			$this->getDC()->setFirstSorting($strFirstSorting, $strFirstSortingOrder);
		}

		// Switch mode
		switch ($this->getDC()->arrDCA['list']['sorting']['mode'])
		{
			case 0:
			case 1:
			case 2:
			case 3:
				$this->viewList();
				break;

			case 4:
				$this->viewParent();
				break;

			case 5:
				$this->treeViewM5();
				break;

			default:
				return vsprintf($this->notImplMsg, 'showAll - Mode ' . $this->getDC()->arrDCA['list']['sorting']['mode']);
				break;
		}
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * AJAX
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	public function ajaxTreeView($intID, $intLevel)
	{
		// Load current informations
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		$strToggleID = $this->getDC()->getTable() . '_tree';

		$arrToggle = $this->Session->get($strToggleID);
		if (!is_array($arrToggle))
		{
			$arrToggle = array();
		}

		$arrToggle[$intID] = 1;

		$this->Session->set($strToggleID, $arrToggle);

		// Init some vars
		$objTableTreeData = $objCurrentDataProvider->getEmptyCollection();
		$objRootConfig = $objCurrentDataProvider->getEmptyConfig();
		$objRootConfig->setId($intID);

		$objModel = $objCurrentDataProvider->fetch($objRootConfig);

		$this->treeWalkModel($objModel, $intLevel, $arrToggle, array('self'));

		foreach ($objModel->getMeta(DCGE::TREE_VIEW_CHILD_COLLECTION) as $objCollection)
		{
			foreach ($objCollection as $objSubModel)
			{
				$objTableTreeData->add($objSubModel);
			}
		}

		$this->getDC()->getEnvironment()->setCurrentCollection($objTableTreeData);
	}

	/**
	 * Loads the current model from the data provider and overrides the selector
	 *
	 * @param type $strSelector the name of the checkbox toggling the palette.
	 */
	public function generateAjaxPalette($strSelector)
	{
		// Check
		$this->checkIsWritable();
		$this->checkLanguage($this->getDC());

		// Load fields and co
		$this->getDC()->loadEditableFields();
		$this->getDC()->setWidgetID($this->getDC()->getId());

		// Check if we have fields
		if (!$this->getDC()->hasEditableFields())
		{
			$this->redirect($this->getReferer());
		}

		// Load something
		$this->getDC()->preloadTinyMce();

		$objDataProvider = $this->getDC()->getDataProvider();

		// Load record from data provider
		$objDBModel = $objDataProvider->fetch($objDataProvider->getEmptyConfig()->setId($this->getDC()->getId()));
		if ($objDBModel == null)
		{
			$objDBModel = $objDataProvider->getEmptyModel();
		}

		$this->getDC()->setCurrentModel($objDBModel);

		// override the setting from POST now.
		$objDBModel->setProperty($strSelector, intval($this->Input->post('state')));
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Edit modes
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Load an older version
	 */
	protected function loadVersion($intID, $mixVersion)
	{
		$objCurrentDataProvider = $this->getDC()->getDataProvider();


		// Load record from version
		$objVersionModel = $objCurrentDataProvider->getVersion($intID, $mixVersion);

		// Redirect if there is no record with the given ID
		if ($objVersionModel == null)
		{
			$this->log('Could not load record ID ' . $intID . ' of table "' . $this->getDC()->getTable() . '"', 'DC_General - DefaultController - edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$objCurrentDataProvider->save($objVersionModel);
		$objCurrentDataProvider->setVersionActive($intID, $mixVersion);

		// Callback onrestoreCallback
		$arrData = $objVersionModel->getPropertiesAsArray();
		$arrData["id"] = $objVersionModel->getID();

		$this->getDC()->getCallbackClass()->onrestoreCallback($intID, $this->getDC()->getTable(), $arrData, $mixVersion);

		$this->log(sprintf('Version %s of record ID %s (table %s) has been restored', $this->Input->post('version'), $this->getDC()->getId(), $this->getDC()->getTable()), 'DC_General - DefaultController - edit()', TL_GENERAL);

		// Reload page with new recored
		$this->reload();
	}

	/**
	 * Perform low level saving of the current model in a DC.
	 * NOTE: the model will get populated with the new values within this function.
	 * Therefore the current submitted data will be stored within the model but only on
	 * success also be saved into the DB.
	 *
	 * @return bool|ModelInterface Model if the save operation was successful or unnecessary, false otherwise.
	 */
	protected function doSave()
	{
		$objDBModel = $this->getDC()->getEnvironment()->getCurrentModel();

		// Check if table is closed
		if ($this->getDC()->arrDCA['config']['closed'] && !($objDBModel->getID()))
		{
			// TODO show alarm message
			$this->redirect($this->getReferer());
		}

		// if we may not store the value, we keep the changes
		// in the current model and return (DO NOT SAVE!).
		if ($this->getDC()->isNoReload() == true)
		{
			return false;
		}

		// Callback
		$this->getDC()->getCallbackClass()->onsubmitCallback();

		// Refresh timestamp
		if ($this->getDC()->getDataProvider()->fieldExists("tstamp") == true)
		{
			$objDBModel->setProperty("tstamp", time());
		}

		// Callback
		$this->getDC()->getCallbackClass()->onsaveCallback($objDBModel);

		// Check if we have a field with eval->alwaysSave
		foreach ($this->objDC->getFieldList() as $arrFieldSettings)
		{
			if($arrFieldSettings['eval']['alwaysSave'] == true)
			{
				$objDBModel->setMeta(DCGE::MODEL_IS_CHANGED, true);
				break;
			}
		}

//        $this->getNewPosition($objDBModel, 'create', null, false);
		// everything went ok, now save the new record
		if (!$objDBModel->getMeta(DCGE::MODEL_IS_CHANGED) && ($objDBModel->getID()))
		{
			return $objDBModel;
		}

		$this->getDC()->getDataProvider()->save($objDBModel);

		// Check if versioning is enabled
		if (isset($this->getDC()->arrDCA['config']['enableVersioning']) && $this->getDC()->arrDCA['config']['enableVersioning'] == true)
		{
			// Compare version and current record
			$mixCurrentVersion = $this->getDC()->getDataProvider()->getActiveVersion($objDBModel->getID());
			if ($mixCurrentVersion != null)
			{
				$mixCurrentVersion = $this->getDC()->getDataProvider()->getVersion($objDBModel->getID(), $mixCurrentVersion);

				if ($this->getDC()->getDataProvider()->sameModels($objDBModel, $mixCurrentVersion) == false)
				{
					// TODO: FE|BE switch
					$user = \BackendUser::getInstance();
					$this->getDC()->getDataProvider()->saveVersion($objDBModel, $user->username);
				}
			}
			else
			{
				// TODO: FE|BE switch
				$user = \BackendUser::getInstance();
				$this->getDC()->getDataProvider()->saveVersion($objDBModel, $user->username);
			}
		}

		// Return the current model
		return $objDBModel;
	}

	/**
	 * Calculate the new position of an element
	 *
	 * Warning this function needs the cdp (current data provider).
	 * Warning this function needs the pdp (parent data provider).
	 *
	 * Based on backbone87 PR - "creating items in parent modes generates sorting value of 0"
	 *
	 * @param Driver $objCDP - Current data provider
	 * @param Driver $objPDP - Parent data provider
	 * @param ModelInterface $objDBModel - Model of element which should moved
	 * @param mixed $mixAfter - Target element
	 * @param string $strMode - Mode like cut | create and so on
	 * @param integer $intInsertMode - Insert Mode => 1 After | 2 Into
	 * @param mixed $mixParentID - Parent ID of table or element
	 *
	 * @return void
	 */
	protected function getNewPosition($objCDP, $objPDP, $objDBModel, $mixAfter, $mixInto, $strMode, $mixParentID = null, $intInsertMode = null, $blnWithoutReorder = false)
	{
		if (!$objDBModel)
		{
			throw new \RuntimeException('No model provided!');
		}

		// Check if we have a sorting field, if not skip here.
		if (!$objCDP->fieldExists('sorting'))
		{
			return;
		}

		// Load default DataProvider.
		if (is_null($objCDP))
		{
			$objCDP = $this->getDC()->getDataProvider();
		}

		if ($mixAfter === DCGE::INSERT_AFTER_START)
		{
			$mixAfter = 0;
		}

		// Search for the highest sorting. Default - Add to end off all.
		// ToDo: We have to check the child <=> parent condition . To get all sortings for one level.
		// If we get a after 0, add to top.
		if ($mixAfter === 0) {

			// Build filter for conditions
			$arrFilter = array();

			if (in_array($this->getDC()->arrDCA['list']['sorting']['mode'], array(4, 5, 6)))
			{
				$arrConditions = $this->objDC->getRootConditions($objCDP->getEmptyModel()->getProviderName());

				if ($arrConditions)
				{
					foreach ($arrConditions as $arrCondition)
					{
						if (key_exists('remote', $arrCondition))
						{
							$arrFilter[] = array(
								'value'		 => Input::getInstance()->get($arrCondition['remote']),
								'property'	 => $arrCondition['property'],
								'operation'	 => $arrCondition['operation']
							);
						}
						else if (key_exists('remote_value', $arrCondition))
						{
							$arrFilter[] = array(
								'value'		 => Input::getInstance()->get($arrCondition['remote_value']),
								'property'	 => $arrCondition['property'],
								'operation'	 => $arrCondition['operation']
							);
						}
						else
						{
							$arrFilter[] = array(
								'value'		 => $arrCondition['value'],
								'property'	 => $arrCondition['property'],
								'operation'	 => $arrCondition['operation']
							);
						}
					}
				}
			}

			// Build config
			$objConfig = $objCDP->getEmptyConfig();
			$objConfig->setFields(array('sorting'));
			$objConfig->setSorting(array('sorting' => DCGE::MODEL_SORTING_ASC));
			$objConfig->setAmount(1);
			$objConfig->setFilter($arrFilter);

			$objCollection = $objCDP->fetchAll($objConfig);

			if ($objCollection->length())
			{
				$intLowestSorting    = $objCollection->get(0)->getProperty('sorting');
				$intNextSorting      = round($intLowestSorting / 2);
			}
			else
			{
				$intNextSorting = 256;
			}

			// FIXME: lowest sorting is uninitialized here - stefan heimes, what to do?
			// Check if we have a valide sorting.
			if (($intLowestSorting < 2 || $intNextSorting <= 2) && !$blnWithoutReorder)
			{
				// ToDo: Add child <=> parent config.
				$objConfig = $objCDP->getEmptyConfig();
				$objConfig->setFilter($arrFilter);

				$this->reorderSorting($objConfig);
				$this->getNewPosition($objCDP, $objPDP, $objDBModel, $mixAfter, $mixInto, $strMode, $mixParentID, $intInsertMode, true);
				return;
			}
			// Fallback to valid sorting.
			else if ($intNextSorting <= 2)
			{
				$intNextSorting = 256;
			}

			$objDBModel->setProperty('sorting', $intNextSorting);
		}
		// If we get a after, search for the right value.
		else if (!empty($mixAfter))
		{
			// Init some vars.
			$intAfterSorting = 0;
			$intNextSorting = 0;

			// Get "after" sorting value value.
			$objAfterConfig = $objCDP->getEmptyConfig();
			$objAfterConfig->setAmount(1);
			$objAfterConfig->setFilter(array(array(
				'value'      => $mixAfter,
				'property'   => 'id',
				'operation'  => '='
			)));

			$objAfterCollection = $objCDP->fetchAll($objAfterConfig);

			if ($objAfterCollection->length())
			{
				$intAfterSorting = $objAfterCollection->get(0)->getProperty('sorting');
			}

			// Get "next" sorting value value.
			$objNextConfig = $objCDP->getEmptyConfig();
			$objNextConfig->setFields(array('sorting'));
			$objNextConfig->setAmount(1);
			$objNextConfig->setSorting(array('sorting' => DCGE::MODEL_SORTING_ASC));

			$arrFilterSettings = array(array(
				'value'      => $intAfterSorting,
				'property'	 => 'sorting',
				'operation'	 => '>'
			));


			$arrFilterChildCondition = array();

			// If we have mode 4, 5, 6 build the child <=> parent condition.
			if (in_array($this->getDC()->arrDCA['list']['sorting']['mode'], array(4, 5, 6)))
			{
				$arrChildCondition	 = $this->objDC->getParentChildCondition($objAfterCollection->get(0), $objCDP->getEmptyModel()->getProviderName());
				$arrChildCondition	 = $arrChildCondition['setOn'];

				if ($arrChildCondition)
				{
					foreach ($arrChildCondition as $arrOperation)
					{
						if (array_key_exists('to_field', $arrOperation))
						{
							$arrFilterChildCondition[] = array(
								'value'		 => $objAfterCollection->get(0)->getProperty($arrOperation['to_field']),
								'property'	 => $arrOperation['to_field'],
								'operation'	 => '='
							);
						}
						else
						{
							$arrFilterChildCondition[] = array(
								'value'		 => $arrOperation['property'],
								'property'	 => $arrOperation['to_field'],
								'operation'	 => '='
							);
						}
					}
				}
			}

			$objNextConfig->setFilter(array_merge($arrFilterSettings, $arrFilterChildCondition));

			$objNextCollection = $objCDP->fetchAll($objNextConfig);

			if ($objNextCollection->length())
			{
				$intNextSorting = $objNextCollection->get(0)->getProperty('sorting');
			}
			else
			{
				$intNextSorting = $intAfterSorting + (2 * 256);
			}

			// Check if we have a valide sorting.
			if (($intAfterSorting < 2 || $intNextSorting < 2 || round(($intNextSorting - $intAfterSorting) / 2) <= 2) && !$blnWithoutReorder)
			{
				// ToDo: Add child <=> parent config.
				$objConfig = $objCDP->getEmptyConfig();
				$objConfig->setFilter($arrFilterChildCondition);

				$this->reorderSorting($objConfig);
				$this->getNewPosition($objCDP, $objPDP, $objDBModel, $mixAfter, $mixInto, $strMode, $mixParentID, $intInsertMode, true);
				return;
			}
			// Fallback to valid sorting.
			else if ($intNextSorting <= 2)
			{
				$intNextSorting = 256;
			}

			// Get sorting between these two values.
			$intNewSorting = $intAfterSorting + round(($intNextSorting - $intAfterSorting) / 2);

			// Save in model.
			$objDBModel->setProperty('sorting', $intNewSorting);

		}
		// Else use the highest value. Fallback.
		else
		{
			$objConfig = $objCDP->getEmptyConfig();
			$objConfig->setFields(array('sorting'));
			$objConfig->setSorting(array('sorting' => DCGE::MODEL_SORTING_DESC));
			$objConfig->setAmount(1);

			$objCollection = $objCDP->fetchAll($objConfig);

			$intHighestSorting = 0;

			if ($objCollection->length())
			{
				$intHighestSorting = $objCollection->get(0)->getProperty('sorting') + 256;
			}

			$objDBModel->setProperty('sorting', $intHighestSorting);
		}
	}

	/**
	 * Reorder all sortings for one table.
	 *
	 * @param Config $objConfig
	 *
	 * @return void
	 */
	protected function reorderSorting($objConfig)
	{
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		if ($objConfig == null)
		{
			$objConfig = $objCurrentDataProvider->getEmptyConfig();
		}

		// Search for the lowest sorting
		$objConfig->setFields(array('sorting'));
		$objConfig->setSorting(array('sorting' => DCGE::MODEL_SORTING_ASC, 'id' => DCGE::MODEL_SORTING_ASC));
		$arrCollection = $objCurrentDataProvider->fetchAll($objConfig);

		$i = 1;
		$intCount = 256;

		foreach ($arrCollection as $value)
		{
			$value->setProperty('sorting', $intCount * $i++);
			$objCurrentDataProvider->save($value);
		}
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Copy modes
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * @todo Make it fine
	 *
	 * @param type $intSrcID
	 * @param type $intDstID
	 * @param type $intMode
	 * @param type $blnChilds
	 * @param type $strDstField
	 * @param type $strSrcField
	 * @param type $strOperation
	 */
	protected function insertCopyModel($intIdSource, $intIdTarget, $intMode, $blnChilds, $strFieldId, $strFieldPid, $strOperation)
	{
		// Get dataprovider
		$objDataProvider = $this->getDC()->getDataProvider();

		// Load the source model
		$objSrcModel = $objDataProvider->fetch($objDataProvider->getEmptyConfig()->setId($intIdSource));

		// Create a empty model for the copy
		$objCopyModel = clone $objSrcModel;

//		// Load all params
//		$arrProperties = $objSrcModel->getPropertiesAsArray();
//
//		// Clear some fields, see dca
//		foreach ($arrProperties as $key => $value)
//		{
//			// If the field is not known, remove it
//			if (!key_exists($key, $this->getDC()->arrDCA['fields']))
//			{
//				continue;
//			}
//
//			// Check doNotCopy
//			if ($this->getDC()->arrDCA['fields'][$key]['eval']['doNotCopy'] == true)
//			{
//				unset($arrProperties[$key]);
//				continue;
//			}
//
//			// Check fallback
//			if ($this->getDC()->arrDCA['fields'][$key]['eval']['fallback'] == true)
//			{
//				$objDataProvider->resetFallback($key);
//			}
//
//			// Check unique
//			if ($this->getDC()->arrDCA['fields'][$key]['eval']['unique'] == true && $objDataProvider->isUniqueValue($key, $value))
//			{
//				throw new \RuntimeException(vsprintf($GLOBALS['TL_LANG']['ERR']['unique'], $key));
//			}
//		}
//
//		// Add the properties to the empty model
//		$objCopyModel->setPropertiesAsArray($arrProperties);

		$intListMode = $this->getDC()->arrDCA['list']['sorting']['mode'];

		//Insert After => Get the parent from he target id
		if (in_array($intListMode, array(0, 1, 2, 3)))
		{
			// ToDo: reset sorting for new entry
		}
		//Insert After => Get the parent from he target id
		else if (in_array($intListMode, array(5)) && $intMode == 1)
		{
			$this->setParent($objCopyModel, $this->getParent('self', null, $intIdTarget), 'self');
		}
		// Insert Into => use the pid
		else if (in_array($intListMode, array(5)) && $intMode == 2)
		{
			if ($this->isRootEntry('self', $intIdTarget))
			{
				$this->setRoot($objCopyModel, 'self');
			}
			else
			{
				$objParentConfig = $this->getDC()->getDataProvider()->getEmptyConfig();
				$objParentConfig->setId($intIdTarget);

				$objParentModel = $this->getDC()->getDataProvider()->fetch($objParentConfig);

				$this->setParent($objCopyModel, $objParentModel, 'self');
			}
		}
		else
		{
			$this->log('Unknown create mode for copy in ' . $this->getDC()->getTable(), 'DC_General - DefaultController - copy()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$objDataProvider->save($objCopyModel);

		$this->arrInsertIDs[$objCopyModel->getID()] = true;

		if ($blnChilds == true)
		{
			$strFilter = $strFieldPid . $strOperation . $objSrcModel->getProperty($strFieldId);
			$objChildConfig = $objDataProvider->getEmptyConfig()->setFilter(array($strFilter));
			$objChildCollection = $objDataProvider->fetchAll($objChildConfig);

			foreach ($objChildCollection as $key => $value)
			{
				if (array_key_exists($value->getID(), $this->arrInsertIDs))
				{
					continue;
				}

				$this->insertCopyModel($value->getID(), $objCopyModel->getID(), 2, $blnChilds, $strFieldId, $strFieldPid, $strOperation);
			}
		}
	}

	protected function getFilteredDataConfig()
	{
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		$objConfig = $objCurrentDataProvider->getEmptyConfig()
			->setFilter($this->getFilter())
			->setSorting(array($this->getDC()->getFirstSorting() => $this->getDC()->getFirstSortingOrder()));



		return $objConfig;
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * showAll Modes
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Controller function for mode 0-3.
	 *
	 * @todo set global current in DC_General
	 * @todo $strTable is unknown
	 */
	protected function viewList()
	{
		// Setup
		$objCurrentDataProvider = $this->getDC()->getDataProvider();
		$objParentDataProvider  = $this->getDC()->getDataProvider('parent');

		$objConfig = $objCurrentDataProvider->getEmptyConfig();
		$this->getDC()->getEnvironment()->getPanelContainer()->initialize($objConfig);

		// If we did not get the sorting from panels use default one from dc.
		$arrSorting = $objConfig->getSorting();
		if(empty($arrSorting))
		{
			$objConfig->setSorting(array($this->getDC()->getFirstSorting() => $this->getDC()->getFirstSortingOrder()));
		}

		$objCollection = $objCurrentDataProvider->fetchAll($this->addParentFilter(
			$this->getEnvironment()->getInputProvider()->getParameter('id'),
			$objConfig
		));

		$this->getDC()->getEnvironment()->setCurrentCollection($objCollection);

		// If we want to group the elements, do so now.
		// Fixme: Never used.
		if (isset($objCondition) && ($this->getEnvironment()->getDataDefinition()->getSortingMode() == 3))
		{
			foreach ($objCollection as $objModel)
			{
				/** @var ModelInterface $objModel */
				$arrFilter = $objCondition->getInverseFilter($objModel);
				$objConfig = $objParentDataProvider->getEmptyConfig()->setFilter($arrFilter);
				$objParent = $objParentDataProvider->fetch($objConfig);

				// TODO: wouldn't it be wiser to link the model instance instead of the id of the parenting model?
				$objModel->setMeta(DCGE::MODEL_PID, $objParent->getId());
			}
		}

		return;

		$showFields = $this->getDC()->arrDCA['list']['label']['fields'];

		// Rename each pid to its label and resort the result (sort by parent table)
		if ($this->getDC()->arrDCA['list']['sorting']['mode'] == 3)
		{
			$this->getDC()->setFirstSorting('pid');

			foreach ($objCollection as $objModel)
			{
				$objFieldConfig = $objParentDataProvider->getEmptyConfig()
					->setId($objModel->getID());

				$objFieldModel = $objParentDataProvider->fetch($objFieldConfig);

				$objModel->setProperty('pid', $objFieldModel->getProperty($showFields[0]));
			}

			$this->arrColSort = array(
				'field' => 'pid',
				'reverse' => false
			);

			$objCollection->sort(array($this, 'sortCollection'));
		}

		if (is_array($showFields))
		{
			// Label
			foreach ($showFields as $v)
			{
				// Decrypt each value
				if ($this->getDC()->arrDCA['fields'][$v]['eval']['encrypt'])
				{
					foreach ($objCollection as $objModel)
					{
						$mixValue = $objModel->getProperty($v);

						$mixValue = deserialize($mixValue);
						$mixValue = $this->objEncrypt->decrypt($mixValue);

						$objModel->setProperty($v, $mixValue);
					}
				}

				// ToDo: $strTable is unknown
//				if (strpos($v, ':') !== false)
//				{
//					list($strKey, $strTable) = explode(':', $v);
//					list($strTable, $strField) = explode('.', $strTable);
//
//
//					$objModel = $this->getDC()->getDataProvider($strTable)->fetch(
//						$this->getDC()->getDataProvider()->getEmptyConfig()
//							->setId($row[$strKey])
//							->setFields(array($strField))
//					);
//
//					$objModelRow->setMeta(DCGE::MODEL_LABEL_ARGS, (($objModel->hasProperties()) ? $objModel->getProperty($strField) : ''));
//				}
			}
		}

		$this->getDC()->setCurrentCollection($objCollection);
	}

	protected function treeViewM5()
	{
		// Load some infromations from DCA
		$arrNeededFields = $this->calcNeededFields($this->getDC()->getDataProvider()->getEmptyModel(), $this->getDC()->getTable());

		// TODO: @CS we need this to be srctable_dsttable_tree for interoperability, for mode5 this will be self_self_tree but with strTable.
		$strToggleID = $this->getDC()->getTable() . '_tree';

		$arrToggle = $this->Session->get($strToggleID);
		if (!is_array($arrToggle))
		{
			$arrToggle = array();
		}

		// Check if the open/close all is active
		if ($this->blnShowAllEntries == true)
		{
			if (key_exists('all', $arrToggle))
			{
				$arrToggle = array();
			}
			else
			{
				$arrToggle = array();
				$arrToggle['all'] = 1;
			}

			// Save in session and redirect
			$this->Session->set($strToggleID, $arrToggle);
			$this->redirectHome();
		}

		// Init some vars
		$objTableTreeData = $this->getDC()->getDataProvider()->getEmptyCollection();
		$objRootConfig = $this->getDC()->getDataProvider()->getEmptyConfig();

		// TODO: @CS rebuild to new layout of filters here.
		// Set fields limit
		$objRootConfig->setFields(array_keys(array_flip($arrNeededFields)));

		$this->getEnvironment()->getPanelContainer()->initialize($objRootConfig);
		$objRootCondition = $this->getDC()->getEnvironment()->getDataDefinition()->getRootCondition();

		if ($objRootCondition)
		{
			$arrBaseFilter = $objRootConfig->getFilter();
			$arrFilter     = $objRootCondition->getFilter();

			if ($arrBaseFilter)
			{
				$arrFilter = array_merge($arrBaseFilter, $arrFilter);
			}

			$objRootConfig->setFilter(array(array(
				'operation' => 'AND',
				'children'    => $arrFilter,
			)));
		}

		// Fetch all root elements
		$objRootCollection = $this->getDC()->getDataProvider()->fetchAll($objRootConfig);

		foreach ($objRootCollection as $objRootModel)
		{
			$objTableTreeData->add($objRootModel);
			$this->treeWalkModel($objRootModel, 0, $arrToggle, array('self'));
		}

		$this->getEnvironment()->setCurrentCollection($objTableTreeData);
	}

	protected function calcLabelFields($strTable)
	{
		if ($strTable == $this->getDC()->getTable())
		{
			// easy, take from DCA.
			return $this->getDC()->arrDCA['list']['label']['fields'];
		}

		$arrChildDef = $this->getDC()->arrDCA['dca_config']['child_list'];
		if (is_array($arrChildDef) && array_key_exists($strTable, $arrChildDef) && isset($arrChildDef[$strTable]['fields']))
		{
			// check if defined in child conditions.
			return $arrChildDef[$strTable]['fields'];
		}
		else if (($strTable == 'self') && is_array($arrChildDef) && array_key_exists('self', $arrChildDef) && $arrChildDef['self']['fields'])
		{
			return $arrChildDef['self']['fields'];
		}
	}

	protected function calcLabelPattern($strTable)
	{
		if ($strTable == $this->getDC()->getTable())
		{
			// easy, take from DCA.
			return $this->getDC()->arrDCA['list']['label']['format'];
		}

		$arrChildDef = $this->getDC()->arrDCA['dca_config']['child_list'];
		if (is_array($arrChildDef) && array_key_exists($strTable, $arrChildDef) && isset($arrChildDef[$strTable]['format']))
		{
			// check if defined in child conditions.
			return $arrChildDef[$strTable]['format'];
		}
		else if (($strTable == 'self') && array_key_exists('self', $arrChildDef) && $arrChildDef['self']['format'])
		{
			return $arrChildDef['self']['format'];
		}
	}

	/**
	 * Get a list with all needed fields for the models.
	 *
	 * @param ModelInterface $objModel
	 * @param string $strDstTable
	 *
	 * @return array A list with all needed values.
	 */
	protected function calcNeededFields(ModelInterface $objModel, $strDstTable)
	{
		$arrFields = $this->calcLabelFields($strDstTable);
		$arrChildCond = $this->getDC()->getChildCondition($objModel, $strDstTable);
		foreach ($arrChildCond as $arrCond)
		{
			if ($arrCond['property'])
			{
				$arrFields[] = $arrCond['property'];
			}
		}

		// Add some default values, if we have this values in DB.
		if($this->objDC->getDataProvider($strDstTable)->fieldExists('enabled'))
		{
			$arrFields [] = 'enabled';
		}

		return $arrFields;
	}

	protected function buildLabel(ModelInterface $objModel)
	{
		// Build full lable
		$arrFields = array();
		foreach ($this->calcLabelFields($objModel->getProviderName()) as $strField)
		{
			$arrFields[] = $objModel->getProperty($strField);
		}
		$objModel->setMeta(DCGE::TREE_VIEW_TITLE, vsprintf($this->calcLabelPattern($objModel->getProviderName()), $arrFields));

		// Callback - let it override the just generated label
		$strLabel = $this->getDC()->getCallbackClass()->labelCallback($objModel, $objModel->getMeta(DCGE::TREE_VIEW_TITLE), $arrFields);
		if ($strLabel != '')
		{
			$objModel->setMeta(DCGE::TREE_VIEW_TITLE, $strLabel);
		}
	}

	/**
	 * This "renders" a model for tree view.
	 *
	 * @param ModelInterface $objModel     the model to render.
	 *
	 * @param int   $intLevel     the current level in the tree hierarchy.
	 *
	 * @param array $arrToggle    the array that determines the current toggle states for the table of the given model.
	 *
	 * @param array $arrSubTables the tables that shall be rendered "below" this item.
	 *
	 */
	protected function treeWalkModel(ModelInterface $objModel, $intLevel, $arrToggle, $arrSubTables = array())
	{
		$blnHasChild = false;

		$objModel->setMeta(DCGE::TREE_VIEW_LEVEL, $intLevel);

		$this->buildLabel($objModel);

		if ($arrToggle['all'] == 1 && !(array_key_exists($objModel->getID(), $arrToggle) && $arrToggle[$objModel->getID()] == 0))
		{
			$objModel->setMeta(DCGE::TREE_VIEW_IS_OPEN, true);
		}

		// Get toogle state
		else if (array_key_exists($objModel->getID(), $arrToggle) && $arrToggle[$objModel->getID()] == 1)
		{
			$objModel->setMeta(DCGE::TREE_VIEW_IS_OPEN, true);
		}
		else
		{
			$objModel->setMeta(DCGE::TREE_VIEW_IS_OPEN, false);
		}

		$arrChildCollections = array();
		foreach ($arrSubTables as $strSubTable)
		{
			// evaluate the child filter for this item.
			$arrChildFilter = $this->getDC()->getChildCondition($objModel, $strSubTable);

			// if we do not know how to render this table within here, continue with the next one.
			if (!$arrChildFilter)
			{
				continue;
			}

			// Create a new Config
			$objChildConfig = $this->getDC()->getDataProvider($strSubTable)->getEmptyConfig();
			$objChildConfig->setFilter($arrChildFilter);

			$objChildConfig->setFields($this->calcNeededFields($objModel, $strSubTable));

			$objChildConfig->setSorting(array('sorting' => 'ASC'));

			// Fetch all children
			$objChildCollection = $this->getDC()->getDataProvider($strSubTable)->fetchAll($objChildConfig);

			// Speed up
			if ($objChildCollection->length() > 0 && !$objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN))
			{
				$blnHasChild = true;
				break;
			}
			else if ($objChildCollection->length() > 0)
			{
				$blnHasChild = true;

				// TODO: @CS we need this to be srctable_dsttable_tree for interoperability, for mode5 this will be self_self_tree but with strTable.
				$strToggleID = $this->getDC()->getTable() . '_tree';

				$arrSubToggle = $this->Session->get($strToggleID);
				if (!is_array($arrSubToggle))
				{
					$arrSubToggle = array();
				}

				foreach ($objChildCollection as $objChildModel)
				{
					// let the child know about it's parent.
					$objModel->setMeta(DCGE::MODEL_PID, $objModel->getID());
					$objModel->setMeta(DCGE::MODEL_PTABLE, $objModel->getProviderName());

					// TODO: determine the real subtables here.
					$this->treeWalkModel($objChildModel, $intLevel + 1, $arrSubToggle, $arrSubTables);
				}
				$arrChildCollections[] = $objChildCollection;

				// speed up, if not open, one item is enough to break as we have some childs.
				if (!$objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN))
				{
					break;
				}
			}
		}

		// If open store children
		if ($objModel->getMeta(DCGE::TREE_VIEW_IS_OPEN) && count($arrChildCollections) != 0)
		{
			$objModel->setMeta(DCGE::TREE_VIEW_CHILD_COLLECTION, $arrChildCollections);
		}

		$objModel->setMeta(DCGE::TREE_VIEW_HAS_CHILDS, $blnHasChild);
	}

	/**
	 * @param mixed $idParent
	 *
	 * @param ConfigInterface $objConfig
	 *
	 * @return \DcGeneral\Data\ConfigInterface
	 */
	protected function addParentFilter($idParent, $objConfig)
	{
		// Setup
		$objCurrentDataProvider = $this->getDC()->getDataProvider();
		$objParentDataProvider  = $this->getDC()->getDataProvider('parent');

		if ($objParentDataProvider)
		{
			$objParent = $objParentDataProvider->fetch($objParentDataProvider->getEmptyConfig()->setId($idParent));

			$objCondition = $this->getDC()->getEnvironment()->getDataDefinition()->getChildCondition(
				$objParentDataProvider->getEmptyModel()->getProviderName(),
				$objCurrentDataProvider->getEmptyModel()->getProviderName()
			);

			if ($objCondition)
			{
				$arrBaseFilter = $objConfig->getFilter();
				$arrFilter     = $objCondition->getFilter($objParent);

				if ($arrBaseFilter)
				{
					$arrFilter = array_merge($arrBaseFilter, $arrFilter);
				}

				$objConfig->setFilter(array(array(
					'operation' => 'AND',
					'children'    => $arrFilter,
				)));
			}
		}

		return $objConfig;
	}

	/**
	 * Show header of the parent table and list all records of the current table
	 *
	 * @return string
	 *
	 * @throws \RuntimeException
	 */
	protected function viewParent()
	{
		// FIXME: changed to use the input provider based id and not the constant, check if this is really correct.
//		if (!CURRENT_ID)
		if (!$this->getEnvironment()->getInputProvider()->getParameter('id'))
		{
			throw new \RuntimeException("mode 4 need a proper parent id defined, somehow none is defined?", 1);
		}

		if (!($objParentDP = $this->getDC()->getDataProvider('parent')))
		{
			throw new \RuntimeException("mode 4 need a proper parent dataprovider defined, somehow none is defined?", 1);
		}

		// Setup
		$objCurrentDataProvider = $this->getDC()->getDataProvider();

		$objConfig = $objCurrentDataProvider->getEmptyConfig();
		$this->getDC()->getEnvironment()->getPanelContainer()->initialize($objConfig);

		// If we did not get the sorting from panels use default one from dc.
		$arrSorting = $objConfig->getSorting();
		if(empty($arrSorting))
		{
			$objConfig->setSorting(array($this->getDC()->getFirstSorting() => $this->getDC()->getFirstSortingOrder()));
		}

		$objCollection = $objCurrentDataProvider->fetchAll($this->addParentFilter(
			$this->getEnvironment()->getInputProvider()->getParameter('id'),
			$objConfig
		));

		$this->getDC()->getEnvironment()->setCurrentCollection($objCollection);
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Panels
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Check all submits from the panels. Save all vlaues into the Session.
	 * Reload the Website.
	 *
	 * @return void
	 * @todo The part with tl_filters123 seems to be obsolete.
	 */
	protected function checkPanelSubmit()
	{
		// FIXME: deactivated.
		return;
		// Check if we have a submit
		// FIXME: dependency injection.
		if (!in_array(\Input::getInstance()->post('FORM_SUBMIT'), array('tl_filters')))
		{
			return;
		}

		// Session
		// FIXME: dependency injection.
		$arrSession = \Session::getInstance()->getData();

		// Set limit from user input
		// FIXME: dependency injection.
		if (strlen(\Input::getInstance()->post('tl_limit')) != 0)
		{
			$strFilter = ($this->getDC()->arrDCA['list']['sorting']['mode'] == 4) ? $this->getDC()->getTable() . '_' . CURRENT_ID : $this->getDC()->getTable();

			// FIXME: dependency injection.
			if (\Input::getInstance()->post('tl_limit') != 'tl_limit')
			{
				$arrSession['filter'][$strFilter]['limit'] = $this->Input->post('tl_limit');
			}
			else
			{
				unset($arrSession['filter'][$strFilter]['limit']);
			}
		}

		// Set sorting from user input
		// FIXME: dependency injection.
		if (strlen(\Input::getInstance()->post('tl_sort')) != 0)
		{
			$arrSession['sorting'][$this->getDC()->getTable()] = in_array(
				$this->getDC()->arrDCA['fields'][\Input::getInstance()->post('tl_sort')]['flag'],
				array(2, 4, 6, 8, 10, 12)
			)
			? \Input::getInstance()->post('tl_sort') . ' DESC'
			: \Input::getInstance()->post('tl_sort');
		}

		// Get sorting fields
		$arrFilterFields = array();
		foreach ($this->getDC()->arrDCA['fields'] as $k => $v)
		{
			if ($v['filter'])
			{
				$arrFilterFields[] = $k;
			}
		}

		foreach ($arrFilterFields as $field)
		{
			// FIXME: dependency injection.
			if (\Input::getInstance()->post($field, true) != 'tl_' . $field)
			{
				$arrSession['filter'][$strFilter][$field] = \Input::getInstance()->post($field, true);
			}
			else
			{
				unset($arrSession['filter'][$strFilter][$field]);
			}
		}

		// Store search value in the current session
		$arrSession['search'][$this->getDC()->getTable()]['value'] = '';
		$arrSession['search'][$this->getDC()->getTable()]['field'] = \Input::getInstance()->post('tl_field', true);

		// Make sure the regular expression is valid
		// FIXME: dependency injection.
		if (\Input::getInstance()->postRaw('tl_value') != '')
		{
			$arrSession['search'][$this->getDC()->getTable()]['value'] = \Input::getInstance()->postRaw('tl_value');
		}

		// FIXME: dependency injection.
		\Session::getInstance()->setData($arrSession);

		// Reload
		$this->reload();
	}

	/**
	 * Generate all information for the filter panel.
	 *
	 * @param type $type
	 * @return type
	 */
	protected function generatePanelFilter($type = 'add')
	{
		// Init
		$arrSortingFields = array();
		// TODO: dependency injection.
		$arrSession = \Session::getInstance()->getData();

		// Setup
		$this->getDC()->setButtonId('tl_buttons_a');
		$strFilter = ($this->getDC()->arrDCA['list']['sorting']['mode'] == 4) ? $this->getDC()->getTable() . '_' . CURRENT_ID : $this->getDC()->getTable();

		// Get sorting fields
		foreach ($this->getDC()->arrDCA['fields'] as $k => $v)
		{
			if ($v['filter'])
			{
				$arrSortingFields[] = $k;
			}
		}

		// Return if there are no sorting fields
		if (empty($arrSortingFields))
		{
			return array();
		}

		// Set filter
		if ($type == 'set')
		{
			$this->filterMenuSetFilter($arrSortingFields, $arrSession, $strFilter);
			return null;
		}

		// Add options
		if ($type == 'add')
		{
			$arrPanelView = $this->filterMenuAddOptions($arrSortingFields, $arrSession, $strFilter);
			return $arrPanelView;
		}
	}

	/**
	 * Set filter from user input and table configuration for filter menu
	 *
	 * @param array $arrSortingFields
	 * @param array $arrSession
	 * @param string $strFilter
	 * @return array
	 */
	protected function filterMenuSetFilter($arrSortingFields, $arrSession, $strFilter)
	{
		// Set filter from table configuration
		foreach ($arrSortingFields as $field)
		{
			// Custom filter method - skip to next field if no further processing is desired.
			if ($this->getDC()->getCallbackClass()->customFilterCallback($field, $arrSession['filter'][$strFilter]))
			{
				continue;
			}
			elseif (isset($arrSession['filter'][$strFilter][$field]))
			{
				// Sort by day
				if (in_array($this->arrDCA['fields'][$field]['flag'], array(5, 6)))
				{
					if ($arrSession['filter'][$strFilter][$field] == '')
					{
						$this->getDC()->setFilter(array(array('operation' => '=', 'property' => $field, 'value' => '')));
					}
					else
					{
						// FIXME: dependency injection?
						$objDate = new \Contao\Date($arrSession['filter'][$strFilter][$field]);
						$this->getDC()->setFilter(array(
							array('operation' => '>', 'property' => $field, 'value' => $objDate->dayBegin),
							array('operation' => '<', 'property' => $field, 'value' => $objDate->dayEnd)
						));
					}
				}

				// Sort by month
				elseif (in_array($this->arrDCA['fields'][$field]['flag'], array(7, 8)))
				{
					if ($arrSession['filter'][$strFilter][$field] == '')
					{
						$this->getDC()->setFilter(array(array('operation' => '=', 'property' => $field, 'value' => '')));
					}
					else
					{
						// FIXME: dependency injection?
						$objDate = new \Contao\Date($arrSession['filter'][$strFilter][$field]);
						$this->getDC()->setFilter(array(
							array('operation' => '>', 'property' => $field, 'value' => $objDate->monthBegin),
							array('operation' => '<', 'property' => $field, 'value' => $objDate->monthEnd)
						));
					}
				}

				// Sort by year
				elseif (in_array($this->arrDCA['fields'][$field]['flag'], array(9, 10)))
				{
					if ($arrSession['filter'][$strFilter][$field] == '')
					{
						$this->getDC()->setFilter(array(array('operation' => '=', 'property' => $field, 'value' => '')));
					}
					else
					{
						$objDate = new Date($arrSession['filter'][$strFilter][$field]);
						$this->getDC()->setFilter(array(
							array('operation' => '>', 'property' => $field, 'value' => $objDate->yearBegin),
							array('operation' => '<', 'property' => $field, 'value' => $objDate->yearEnd)
						));
					}
				}

				// Manual filter
				elseif ($this->arrDCA['fields'][$field]['eval']['multiple'])
				{
					// TODO find in set
					// CSV lists (see #2890)
					/* if (isset($this->dca['fields'][$field]['eval']['csv']))
					  {
					  $this->procedure[] = $this->Database->findInSet('?', $field, true);
					  $this->values[] = $session['filter'][$filter][$field];
					  }
					  else
					  {
					  $this->procedure[] = $field . ' LIKE ?';
					  $this->values[] = '%"' . $session['filter'][$filter][$field] . '"%';
					  } */
				}

				// Other sort algorithm
				else
				{
					$this->getDC()->setFilter(
						array(
							array('operation' => '=', 'property' => $field, 'value' => $arrSession['filter'][$strFilter][$field])
						)
					);
				}
			}
		}

		if ($arrSession['search'][$this->getDC()->getTable()]['value'] != '')
		{
			$this->getDC()->setFilter(
				array(
					array(
						'operation' => 'LIKE',
						'property'  => $arrSession['search'][$this->getDC()->getTable()]['field'],
						'value'     => sprintf('*%s*', $arrSession['search'][$this->getDC()->getTable()]['value'])
					)
				)
			);
		}

		return $arrSession;
	}

	/**
	 * Add sorting options to filter menu
	 *
	 * @param array $arrSortingFields
	 * @param array $arrSession
	 * @param string $strFilter
	 * @return array
	 */
	protected function filterMenuAddOptions($arrSortingFields, $arrSession, $strFilter)
	{
		$arrPanelView = array();

		// Add sorting options
		foreach ($arrSortingFields as $cnt => $field)
		{
			$arrProcedure = array();

			if ($this->getDC()->arrDCA['list']['sorting']['mode'] == 4)
			{
				$arrProcedure[] = array('operation' => '=', 'property' => 'pid', 'value' => CURRENT_ID);
			}

			if (!is_null($this->getDC()->getRootIds()) && is_array($this->getDC()->getRootIds()))
			{
				$arrProcedure[] = array('operation' => 'IN', 'property' => 'id', 'values' => array_map('intval', $this->getDC()->getRootIds()));
			}

			foreach ((array)$this->getFilter() as $arrSubFilter)
			{
				if ($arrSubFilter['property'] != $field)
				{
					$arrProcedure[] = $arrSubFilter;
				}
			}

			$objCollection = $this->getDC()->getDataProvider()->getFilterOptions(
				$this->getDC()
					->getDataProvider()
					->getEmptyConfig()
					->setFields(array($field))
					->setFilter($arrProcedure)
			);

			// Begin select menu
			$arrPanelView[$field] = array(
				'select' => array(
					'name' => $field,
					'id' => $field,
					'class' => 'tl_select' . (isset($arrSession['filter'][$strFilter][$field]) ? ' active' : '')
				),
				'option' => array(
					array(
						'value' => 'tl_' . $field,
						'content' => (is_array($this->getDC()->arrDCA['fields'][$field]['label']) ? $this->getDC()->arrDCA['fields'][$field]['label'][0] : $this->getDC()->arrDCA['fields'][$field]['label'])
					),
					array(
						'value' => 'tl_' . $field,
						'content' => '---'
					)
				)
			);

			if ($objCollection->length() > 0)
			{
				$options = array();

				foreach ($objCollection as $intIndex => $objModel)
				{
					$options[$intIndex] = $objModel->getProperty($field);
				}

				// Sort by day
				if (in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'], array(5, 6)))
				{
					$this->arrColSort = array(
						'field' => $field,
						'reverse' => ($this->getDC()->arrDCA['fields'][$field]['flag'] == 6) ? true : false
					);

					$objCollection->sort(array($this, 'sortCollection'));

					foreach ($objCollection as $intIndex => $objModel)
					{
						if ($objModel->getProperty($field) == '')
						{
							$options[$objModel->getProperty($field)] = '-';
						}
						else
						{
							$date = $objModel->getProperty($field);
							if ($date instanceof \DateTime)
							{
								$key      = $date->getTimestamp();
							}
							else
							{
								$key      = $date;
							}

							$options[$key] = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $key);
						}

						unset($options[$intIndex]);
					}
				}

				// Sort by month
				elseif (in_array($this->getDC()->arrDCA['fields'][$field]['flag'], array(7, 8)))
				{
					$this->arrColSort = array(
						'field' => $field,
						'reverse' => ($this->getDC()->arrDCA['fields'][$field]['flag'] == 8) ? true : false
					);

					$objCollection->sort(array($this, 'sortCollection'));

					foreach ($objCollection as $intIndex => $objModel)
					{
						if ($objModel->getProperty($field) == '')
						{
							$options[$objModel->getProperty($field)] = '-';
						}
						else
						{
							$options[$objModel->getProperty($field)] = date('Y-m', $objModel->getProperty($field));
							$intMonth = (date('m', $objModel->getProperty($field)) - 1);

							if (isset($GLOBALS['TL_LANG']['MONTHS'][$intMonth]))
							{
								$options[$objModel->getProperty($field)] = $GLOBALS['TL_LANG']['MONTHS'][$intMonth] . ' ' . date('Y', $objModel->getProperty($field));
							}
						}

						unset($options[$intIndex]);
					}
				}

				// Sort by year
				elseif (in_array($this->getDC()->arrDCA['fields'][$field]['flag'], array(9, 10)))
				{
					$this->arrColSort = array(
						'field' => $field,
						'reverse' => ($this->getDC()->arrDCA['fields'][$field]['flag'] == 10) ? true : false
					);

					$objCollection->sort(array($this, 'sortCollection'));

					foreach ($objCollection as $intIndex => $objModel)
					{
						if ($objModel->getProperty($field) == '')
						{
							$options[$objModel->getProperty($field)] = '-';
						}
						else
						{
							$options[$objModel->getProperty($field)] = date('Y', $objModel->getProperty($field));
						}

						unset($options[$intIndex]);
					}
				}

				// Manual filter
				if ($this->getDC()->arrDCA['fields'][$field]['eval']['multiple'])
				{
					$moptions = array();

					foreach ($objCollection as $objModel)
					{
						if (isset($this->getDC()->arrDCA['fields'][$field]['eval']['csv']))
						{
							$doptions = trimsplit($this->getDC()->arrDCA['fields'][$field]['eval']['csv'], $objModel->getProperty($field));
						}
						else
						{
							$doptions = deserialize($objModel->getProperty($field));
						}

						if (is_array($doptions))
						{
							$moptions = array_merge($moptions, $doptions);
						}
					}

					$options = $moptions;
				}

				$options = array_unique($options);
				$arrOptionsCallback = array();

				// Load options callback
				if (is_array($this->getDC()->arrDCA['fields'][$field]['options_callback']) && !$this->getDC()->arrDCA['fields'][$field]['reference'])
				{
					$arrOptionsCallback = $this->getDC()->getCallbackClass()->optionsCallback($field);

					// Sort options according to the keys of the callback array
					if (!is_null($arrOptionsCallback))
					{
						$options = array_intersect(array_keys($arrOptionsCallback), $options);
					}
				}
				$arrOptions = array();
				$arrSortOptions = array();
				$blnDate = in_array($this->getDC()->arrDCA['fields'][$field]['flag'], array(5, 6, 7, 8, 9, 10));

				// Options
				foreach ($options as $kk => $vv)
				{

					$value = $blnDate ? $kk : $vv;

					// Replace the ID with the foreign key
					if (isset($this->getDC()->arrDCA['fields'][$field]['foreignKey']))
					{
						$key = explode('.', $this->getDC()->arrDCA['fields'][$field]['foreignKey'], 2);

						$objModel = $this->getDC()->getDataProvider($key[0])->fetch(
							$this->getDC()->getDataProvider($key[0])->getEmptyConfig()
								->setId($vv)
								->setFields(array($key[1] . ' AS value'))
						);

						if ($objModel->hasProperties())
						{
							$vv = $objModel->getProperty('value');
						}
					}

					// Replace boolean checkbox value with "yes" and "no"
					elseif ($this->getDC()->arrDCA['fields'][$field]['eval']['isBoolean'] || ($this->getDC()->arrDCA['fields'][$field]['inputType'] == 'checkbox' && !$this->getDC()->arrDCA['fields'][$field]['eval']['multiple']))
					{
						$vv = ($vv != '') ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
					}

					// Options callback
					elseif (is_array($arrOptionsCallback) && !empty($arrOptionsCallback))
					{
						$vv = $arrOptionsCallback[$vv];
					}

					// Get the name of the parent record
					elseif ($field == 'pid')
					{
						// Load language file and data container array of the parent table
						$this->loadLanguageFile($this->getDC()->getParentTable());
						$this->loadDataContainer($this->getDC()->getParentTable());

						$objParentDC = new DC_General($this->getDC()->getParentTable());
						$arrParentDca = $objParentDC->getDCA();

						$showFields = $arrParentDca['list']['label']['fields'];

						if (!$showFields[0])
						{
							$showFields[0] = 'id';
						}

						$objModel = $this->getDC()->getDataProvider('parent')->fetch(
							$this->getDC()->getDataProvider('parent')->getEmptyConfig()
								->setId($vv)
								->setFields(array($showFields[0]))
						);

						if ($objModel->hasProperties())
						{
							$vv = $objModel->getProperty($showFields[0]);
						}
					}

					$strOptionsLabel = '';

					// Use reference array
					if (isset($this->getDC()->arrDCA['fields'][$field]['reference']))
					{
						$strOptionsLabel = is_array($this->getDC()->arrDCA['fields'][$field]['reference'][$vv]) ? $this->getDC()->arrDCA['fields'][$field]['reference'][$vv][0] : $this->getDC()->arrDCA['fields'][$field]['reference'][$vv];
					}

					// Associative array
					elseif ($this->getDC()->arrDCA['fields'][$field]['eval']['isAssociative'] || array_is_assoc($this->getDC()->arrDCA['fields'][$field]['options']))
					{
						$strOptionsLabel = $this->getDC()->arrDCA['fields'][$field]['options'][$vv];
					}

					// No empty options allowed
					if (!strlen($strOptionsLabel))
					{
						// FIXME: this is a rather evil hack but I ended up here with an array containing files.
						// This usually reensembles an invalid setup but we should not raise warnings then but cope
						// correctly with it. For the moment, we simply ignore such data we can not handle.
						if (!(is_string($vv) || is_numeric($vv)))
						{
							continue;
						}
						$strOptionsLabel = strlen($vv) ? $vv : '-';
					}

					$arrOptions[utf8_romanize($strOptionsLabel)] = array(
						'value' => specialchars($value),
						'select' => ((isset($arrSession['filter'][$strFilter][$field]) && $value == $arrSession['filter'][$strFilter][$field]) ? ' selected="selected"' : ''),
						'content' => $strOptionsLabel
					);

					$arrSortOptions[] = utf8_romanize($strOptionsLabel);
				}

				// Sort by option values
				if (!$blnDate)
				{
					natcasesort($arrSortOptions);

					if (in_array($this->getDC()->arrDCA['fields'][$field]['flag'], array(2, 4, 12)))
					{
						$arrSortOptions = array_reverse($arrSortOptions, true);
					}
				}

				foreach ($arrSortOptions as $value)
				{
					$arrPanelView[$field]['option'][] = $arrOptions[$value];
				}
			}

			// Force a line-break after six elements
			if ((($cnt + 1) % 6) == 0)
			{
				$arrPanelView[] = 'new';
			}
		}

		return $arrPanelView;
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Helper DataProvider
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	/**
	 * Check if a entry has some childs
	 *
	 * @param array $arrFilterPattern
	 * @param ModelInterface $objParentModel
	 *
	 * @return boolean True => has children | False => no children
	 */
	protected function hasChildren($objParentModel, $strTable)
	{
		$arrFilter = array();

		// Build filter Settings
		foreach ($this->getDC()->getJoinConditions($objParentModel, $strTable) as $valueFilter)
		{
			if (isset($valueFilter['srcField']) && $valueFilter['srcField'] != '')
			{
				$arrFilter[] = $valueFilter['dstField'] . $valueFilter['operation'] . $objParentModel->getProperty($valueFilter['srcField']);
			}
			else
			{
				$arrFilter[] = $valueFilter['dstField'] . $valueFilter['operation'];
			}
		}

		// Create a new Config
		$objConfig = $this->getDC()->getDataProvider()->getEmptyConfig();
		$objConfig->setFilter($arrFilter);

		// Fetch all children
		if ($this->getDC()->getDataProvider()->getCount($objConfig) != 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	protected function setParent(ModelInterface  $objChildEntry, ModelInterface  $objParentEntry, $strTable)
	{
		$arrChildCondition = $this->getDC()->getParentChildCondition($objParentEntry, $objChildEntry->getProviderName());
		if (!($arrChildCondition && $arrChildCondition['setOn']))
		{
			throw new \RuntimeException("Can not calculate parent.", 1);
		}

		foreach ($arrChildCondition['setOn'] as $arrCondition)
		{
			if ($arrCondition['from_field'])
			{
				$objChildEntry->setProperty($arrCondition['to_field'], $objParentEntry->getProperty($arrCondition['from_field']));
			}
			else if (!is_null('value', $arrCondition))
			{
				$objChildEntry->setProperty($arrCondition['to_field'], $arrCondition['value']);
			}
			else
			{
				throw new \RuntimeException("Error Processing child condition, neither from_field nor value specified: " . var_export($arrCondition, true), 1);
			}
		}
	}

	// FIXME: get rid of this, it is non working anyway.
	protected function getParent($strTable, $objCurrentModel = null, $intCurrentID = null)
	{
		// Check if something is set
		if ($objCurrentModel == null && $intCurrentID == null)
		{
			return null;
		}

		// If we have only the id load current model
		if ($objCurrentModel == null)
		{
			$objCurrentConfig = $this->getDC()->getDataProvider()->getEmptyConfig();
			$objCurrentConfig->setId($intCurrentID);

			$objCurrentModel = $this->getDC()->getDataProvider()->fetch($objCurrentConfig);
		}

		// Build child to parent
		$strFilter = $arrJoinCondition[0]['srcField'] . $arrJoinCondition[0]['operation'] . $objCurrentModel->getProperty($arrJoinCondition[0]['dstField']);

		// Load model
		$objParentConfig = $this->getDC()->getDataProvider()->getEmptyConfig();
		$objParentConfig->setFilter(array($strFilter));

		return $this->getDC()->getDataProvider()->fetch($objParentConfig);
	}

	protected function isRootEntry($strTable, $mixID)
	{
		// Get the join field
		$arrRootCondition = $this->getDC()->getRootConditions($strTable);

		switch ($arrRootCondition[0]['operation'])
		{
			case '=':
				return ($mixID == $arrRootCondition[0]['value']);

			case '<':
				return ($arrRootCondition[0]['value'] < $mixID);

			case '>':
				return ($arrRootCondition[0]['value'] > $mixID);

			case '!=':
				return ($arrRootCondition[0]['value'] != $mixID);
		}

		return false;
	}

	protected function setRoot(ModelInterface $objCurrentEntry, $strTable)
	{
		$arrRootSetter = $this->getDC()->getRootSetter($strTable);
		if (!($arrRootSetter && $arrRootSetter))
		{
			throw new \RuntimeException("Can not calculate parent.", 1);
		}

		foreach ($arrRootSetter as $arrCondition)
		{
			if (($arrCondition['property'] && isset($arrCondition['value'])))
			{
				$objCurrentEntry->setProperty($arrCondition['property'], $arrCondition['value']);
			}
			else
			{
				throw new \RuntimeException("Error Processing root condition, you need to specify property and value: " . var_export($arrCondition, true), 1);
			}
		}
	}

	/* /////////////////////////////////////////////////////////////////////
	 * ---------------------------------------------------------------------
	 * Helper
	 * ---------------------------------------------------------------------
	 * ////////////////////////////////////////////////////////////////// */

	public function sortCollection(ModelInterface $a, ModelInterface $b)
	{
		if ($a->getProperty($this->arrColSort['field']) == $b->getProperty($this->arrColSort['field']))
		{
			return 0;
		}

		if (!$this->arrColSort['reverse'])
		{
			return ($a->getProperty($this->arrColSort['field']) < $b->getProperty($this->arrColSort['field'])) ? -1 : 1;
		}
		else
		{
			return ($a->getProperty($this->arrColSort['field']) < $b->getProperty($this->arrColSort['field'])) ? 1 : -1;
		}
	}

	public function executePostActions()
	{
		if (version_compare(VERSION, '3.0', '>='))
		{
			$objHandler = new Ajax3X();
		}
		else
		{
			$objHandler = new Ajax2X();
		}
		$objHandler->executePostActions($this->getDC());
	}

}
