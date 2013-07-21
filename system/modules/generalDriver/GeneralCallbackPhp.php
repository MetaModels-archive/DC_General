<?php

/**
 * PHP version 5
 * @package    generalDriver
 * @author     Stefan Heimes <cms@men-at-work.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

class GeneralCallbackPhp extends System implements InterfaceGeneralCallback
{

	/**
	 * The DC
	 *
	 * @var DC_General
	 */
	private $objDC;

	/**
	 * Set the DC
	 *
	 * @param DC_General $objDC
	 */
	public function setDC($objDC)
	{
		$this->objDC = $objDC;
	}

	/**
	 * get the DC
	 *
	 * @return DC_General $objDC
	 */
	public function getDC()
	{
		return $this->objDC;
	}

	/**
	 * Execute the passed callbacks.
	 *
	 * The returned array will hold all result values from all via $varCallbacks defined callbacks.
	 *
	 * @param mixed $varCallbacks Either the name of an HOOK defined in $GLOBALS['TL_HOOKS'] or an array of
	 *                            array('Class', 'method') pairs.
	 *
	 * @return array
	 */
	public function executeCallbacks($varCallbacks)
	{
		if ($varCallbacks === null)
		{
			return array();
		}

		if (is_string($varCallbacks))
		{
			$varCallbacks = $GLOBALS['TL_HOOKS'][$varCallbacks];
		}

		if (!is_array($varCallbacks))
		{
			return array();
		}

		$arrArgs = array_slice(func_get_args(), 1);
		$arrResults = array();

		foreach ($varCallbacks as $arrCallback)
		{
			if (is_callable($arrCallback))
			{
				$arrResults[] = call_user_func_array($arrCallback, $arrArgs);
			}
		}

		return $arrResults;
	}

	/**
	 * Call the customer label callback.
	 *
	 * @param InterfaceGeneralModel $objModelRow The current model for which the label shall get generated for.
	 *
	 * @param string                $mixedLabel  The label string (as defined in DCA).
	 *
	 * @param array                 $args        The arguments for the label string.
	 *
	 * @return string
	 */
	public function labelCallback(InterfaceGeneralModel $objModelRow, $mixedLabel, $args)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();
		$arrCallback = $arrDCA['list']['label']['label_callback'];

		// Check Callback
		if (is_callable($arrCallback))
		{
			if (version_compare(VERSION, '2.10', '>'))
			{
				return call_user_func(
					$arrCallback,
					$objModelRow,
					$mixedLabel,
					$this->getDC(),
					$args
				);
			}
			else
			{
				return call_user_func(
					$arrCallback,
					$objModelRow,
					$mixedLabel,
					$this->getDC()
				);
			}
		}

		return null;
	}

	/**
	 * Call the button callback for the regular operations.
	 *
	 * @param InterfaceGeneralModel $objModelRow          The current model instance for which the button shall be
	 *                                                    generated.
	 *
	 * @param array                 $arrOperation         The operation for which a button shall be generated
	 *                                                    (excerpt from DCA).
	 *
	 * @param string                $strLabel             The label for the button.
	 *
	 * @param string                $strTitle             The title for the button.
	 *
	 * @param array                 $arrAttributes        Attributes for the generated button.
	 *
	 * @param string                $strTable             The dataprovider name of the view.
	 *
	 * @param array                 $arrRootIds           The root ids
	 *
	 * @param array                 $arrChildRecordIds    Ids of the direct children to the model in $objModelRow.
	 *
	 * @param boolean               $blnCircularReference TODO: document parameter $blnCircularReference
	 *
	 * @param string                $strPrevious          TODO: document parameter $strPrevious
	 *
	 * @param string                $strNext              TODO: document parameter $strNext
	 *
	 * @return string|null
	 */
	public function buttonCallback($objModelRow, $arrOperation, $strLabel, $strTitle, $arrAttributes, $strTable, $arrRootIds, $arrChildRecordIds, $blnCircularReference, $strPrevious, $strNext)
	{
		// Check Callback
		if (is_callable($arrOperation['button_callback']))
		{
			return call_user_func(
				$arrOperation['button_callback'],
				$objModelRow,
				$arrOperation['href'],
				$strLabel,
				$strTitle,
				$arrOperation['icon'],
				$arrAttributes,
				$strTable,
				$arrRootIds,
				$arrChildRecordIds,
				$blnCircularReference,
				$strPrevious,
				$strNext
			);
		}

		return null;
	}

	/**
	 * Call the button callback for the global operations.
	 *
	 * @param string $strLabel      Label for the button.
	 *
	 * @param string $strTitle      Title for the button.
	 *
	 * @param array  $arrAttributes Attributes for the button
	 *
	 * @param string $strTable      Name of the current data provider.
	 *
	 * @param array  $arrRootIds    Ids of the root elements in the data provider.
	 *
	 * @return string|null
	 */
	public function globalButtonCallback($strLabel, $strTitle, $arrAttributes, $strTable, $arrRootIds)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Check Callback
		if (is_callable($arrDCA['button_callback']))
		{
			return call_user_func(
				$arrDCA['button_callback'],
				$arrDCA['href'],
				$strLabel,
				$strTitle,
				$arrDCA['icon'],
				$arrAttributes,
				$strTable,
				$arrRootIds
			);
		}

		return null;
	}

	/**
	 * Call the button callback for the paste operations
	 * TODO: this should be included in the interface when the signature has been finished.
	 *
	 * @param DataContainer $dc       DataContainer or DC_General FIXME: why is $dc here? we already have $this->getDC()
	 *
	 * @param array         $row      Array with current data
	 *
	 * @param string        $table    Tablename
	 *
	 * @param unknown       $cr        TODO: document parameter $cr
	 *
	 * @param array         $childs   Clipboard informations
	 *
	 * @param unknown       $previous  TODO: document parameter $previous
	 *
	 * @param unknown       $next      TODO: document parameter $next
	 *
	 * @return string
	 */
	public function pasteButtonCallback($dc, $row, $table, $cr, $childs, $previous, $next)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Check Callback
		if (is_callable($arrDCA['list']['sorting']['paste_button_callback']))
		{
			return call_user_func(
				$arrDCA['list']['sorting']['paste_button_callback'],
				$dc,
				$row,
				$table,
				$cr,
				$childs,
				$previous,
				$next
			);
		}

		return false;
	}

	/**
	 * Call the header callback.
	 *
	 * @param array $arrAdd TODO: document parameter $arrAdd
	 *
	 * @return array|null
	 */
	public function headerCallback($arrAdd)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();
		$arrCallback = $arrDCA['list']['sorting']['header_callback'];

		if (is_callable($arrCallback))
		{
			return call_user_func(
				$arrCallback,
				$arrAdd,
				$this->getDC()
			);
		}

		return null;
	}

	/**
	 * Call the child record callback.
	 *
	 * @param InterfaceGeneralModel $objModel TODO: document parameter $objModel
	 *
	 * @return string|null
	 */
	public function childRecordCallback(InterfaceGeneralModel $objModel)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();
		$arrCallback = $arrDCA['list']['sorting']['child_record_callback'];

		if (is_callable($arrCallback))
		{
			return call_user_func(
				$arrCallback,
				$objModel
			);
		}

		return null;
	}

	/**
	 * Call the options callback for given the field.
	 *
	 * @param string $strField Name of the field for which to call the options callback.
	 *
	 * @return array|null
	 */
	public function optionsCallback($strField)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();
		$arrCallback = $arrDCA['fields'][$strField]['options_callback'];

		// Check Callback
		if (is_callable($arrCallback))
		{
			return call_user_func(
				$arrCallback,
				$this->getDC()
			);
		}

		return null;
	}

	/**
	 * Call the onrestore callback.
	 *
	 * @param integer $intID      ID of current dataset.
	 *
	 * @param string  $strTable   Name of current Table.
	 *
	 * @param array   $arrData    Array with all Data.
	 *
	 * @param integer $intVersion Version which was restored.
	 *
	 * @return void
	 */
	public function onrestoreCallback($intID, $strTable, $arrData, $intVersion)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Check Callback
		if (is_array($arrDCA['config']['onrestore_callback']))
		{
			foreach ($arrDCA['config']['onrestore_callback'] as $callback)
			{
				if (is_callable($callback))
				{
					call_user_func(
						$callback,
						$intID,
						$strTable,
						$arrData,
						$intVersion
					);
				}
			}
		}
	}

	/**
	 * Call the load callback.
	 *
	 * @param string $strField Name of the field for which to call the load callback.
	 *
	 * @param mixed $varValue  Current value to be transformed.
	 *
	 * @return mixed|null
	 */
	public function loadCallback($strField, $varValue)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();
		$arrCallbacks = $arrDCA['fields'][$strField]['load_callback'];

		// Load Callback
		if (is_array($arrCallbacks))
		{
			foreach ($arrCallbacks as $arrCallback)
			{
				if (is_callable($arrCallback))
				{
					$varValue = call_user_func(
						$arrCallback,
						$varValue,
						$this->getDC()
					);
				}
			}

			return $varValue;
		}

		return null;
	}

	/**
	 * Call onload_callback (e.g. to check permissions).
	 *
	 * @return void
	 */
	public function onloadCallback()
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Check Callback
		if (is_array($arrDCA['config']['onload_callback']))
		{
			foreach ($arrDCA['config']['onload_callback'] as $callback)
			{
				if (is_callable($callback))
				{
					call_user_func(
						$arrCallback,
						$this->getDC()
					);
				}
			}
		}
	}

	/**
	 * Call the group callback.
	 *
	 * @param type                  $group TODO: document parameter $group
	 *
	 * @param type                  $mode  TODO: document parameter $mode
	 *
	 * @param type                  $field TODO: document parameter $field
	 *
	 * @param InterfaceGeneralModel $objModelRow
	 *
	 * @return type  TODO: document result
	 */
	public function groupCallback($group, $mode, $field, $objModelRow)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		$currentGroup = $group;

		// Check Callback
		if (is_callable($arrDCA['list']['label']['group_callback']))
		{
			$currentGroup = call_user_func(
				$arrDCA['list']['label']['group_callback'],
				$currentGroup,
				$mode,
				$field,
				$objModelRow,
				$this
			);

			if ($currentGroup == null)
			{
				$group = $currentGroup;
			}
		}

		return $group;
	}

	/**
	 * Call the save callback for a widget.
	 *
	 * @param array $arrConfig Configuration of the widget.
	 *
	 * @param mixed $varNew    The new value that shall be transformed.
	 *
	 * @return mixed
	 */
	public function saveCallback($arrConfig, $varNew)
	{
		if (is_array($arrConfig['save_callback']))
		{
			foreach ($arrConfig['save_callback'] as $arrCallback)
			{
				if (is_callable($arrCallback)) {
					$varNew = call_user_func(
						$arrCallback,
						$varNew,
						$this->getDC()
					);
				}
			}
		}

		return $varNew;
	}

	/**
	 * Call ondelete_callback.
	 *
	 * @return void
	 */
	public function ondeleteCallback()
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Call ondelete_callback
		if (is_array($arrDCA['config']['ondelete_callback']))
		{
			foreach ($arrDCA['config']['ondelete_callback'] as $callback)
			{
				if (is_callable($callback))
				{
					call_user_func(
						$callback,
						$this->getDC()
					);
				}
			}
		}
	}

	/**
	 * Call the onsubmit_callback.
	 *
	 * @return void
	 */
	public function onsubmitCallback()
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		if (is_array($arrDCA['config']['onsubmit_callback']))
		{
			foreach ($arrDCA['config']['onsubmit_callback'] as $callback)
			{
				if (is_callable($callback))
				{
					call_user_func(
						$callback,
						$this->getDC()
					);
				}
			}
		}
	}

	/**
	 * Call the oncreate_callback.
	 *
	 * @param mixed $insertID  The id from the new record.
	 *
	 * @param array $arrRecord The new record.
	 *
	 * @return void
	 */
	public function oncreateCallback($insertID, $arrRecord)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Call the oncreate_callback
		if (is_array($arrDCA['config']['oncreate_callback']))
		{
			foreach ($arrDCA['config']['oncreate_callback'] as $callback)
			{
				if (is_callable($callback))
				{
					call_user_func(
						$callback,
						$this->getDC()->getTable(),
						$insertID,
						$arrRecord,
						$this->getDC()
					);
				}
			}
		}
	}

	/**
	 * Call the onsave_callback
	 *
	 * @param InterfaceGeneralModel $objModel The model that has been updated.
	 *
	 * @return void
	 */
	public function onsaveCallback($objModel)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Call the oncreate_callback
		if (is_array($arrDCA['config']['onsave_callback']))
		{
			foreach ($arrDCA['config']['onsave_callback'] as $callback)
			{
				if (is_callable($callback))
				{
					call_user_func(
						$callback,
						$objModel,
						$this->getDC()
					);
				}
			}
		}
	}


	/**
	 * Get the current palette.
	 *
	 * @param array $arrPalette The current palette.
	 *
	 * @return array The modified palette.
	 */
	public function parseRootPaletteCallback($arrPalette)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Call the oncreate_callback
		if (is_array($arrDCA['config']['parseRootPalette_callback']))
		{
			foreach ($arrDCA['config']['parseRootPalette_callback'] as $callback)
			{
				if (is_callable($callback))
				{
					$mixReturn = call_user_func(
						$callback,
						$this->getDC(),
						$arrPalette
					);

					if (is_array($mixReturn))
					{
						$arrPalette = $mixReturn;
					}
				}
			}
		}

		return $arrPalette;
	}

	/**
	 * Call the onmodel_beforeupdate.
	 *
	 * NOTE: the fact that this method has been called does not mean the values of the model have been changed
	 * it merely just tells "we will load a model (from memory or database) and update it's properties with
	 * those from the POST data".
	 *
	 * After the model has been updated, the onModelUpdateCallback will get triggered.
	 *
	 * @param InterfaceGeneralModel $objModel The model that will get updated.
	 *
	 * @return void
	 */
	public function onModelBeforeUpdateCallback($objModel)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Call the oncreate_callback
		if (is_array($arrDCA['config']['onmodel_beforeupdate']))
		{
			foreach ($arrDCA['config']['onmodel_beforeupdate'] as $callback)
			{
				if (is_callable($callback)) {
					call_user_func(
						$callback,
						$objModel,
						$this->getDC()
					);
				}
			}
		}
	}

	/**
	 * Call the onmodel_update.
	 * NOTE: the fact that this method has been called does not mean the values of the model have been changed
	 * it merely just tells "we have loaded a model (from memory or database) and updated it's properties with
	 * those from the POST data".
	 *
	 * @param InterfaceGeneralModel $objModel The model that has been updated.
	 *
	 * @return void
	 */
	public function onModelUpdateCallback($objModel)
	{
		// Load DCA
		$arrDCA = $this->getDC()->getDCA();

		// Call the oncreate_callback
		if (is_array($arrDCA['config']['onmodel_update']))
		{
			foreach ($arrDCA['config']['onmodel_update'] as $callback)
			{
				if (is_callable($callback)) {
					call_user_func(
						$callback,
						$objModel,
						$this->getDC()
					);
				}
			}
		}
	}
}
