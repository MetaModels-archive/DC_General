<?php

namespace DcGeneral\Panel;

use DcGeneral\Data\ConfigInterface;
use DcGeneral\Panel\AbstractElement;
use DcGeneral\Panel\PanelElementInterface;
use DcGeneral\Panel\LimitElementInterface;
use DcGeneral\View\ViewTemplateInterface;

class DefaultLimitElement extends AbstractElement implements LimitElementInterface
{
	/**
	 * @var int
	 */
	protected $intOffset;

	/**
	 * @var int
	 */
	protected $intAmount;

	/**
	 * @var int
	 */
	protected $intTotal;

	protected function getPersistent()
	{
		$arrValue = array();
		if ($this->getInputProvider()->hasPersistentValue('limit'))
		{
			$arrValue = $this->getInputProvider()->getPersistentValue('limit');
		}

		if (array_key_exists($this->getDataContainer()->getName(), $arrValue))
		{
			return $arrValue[$this->getDataContainer()->getName()];
		}

		return array();
	}

	protected function setPersistent($intOffset, $intAmount)
	{
		$arrValue = array();

		if ($this->getInputProvider()->hasPersistentValue('limit'))
		{
			$arrValue = $this->getInputProvider()->getPersistentValue('limit');
		}

		if ($intOffset)
		{
			if (!is_array($arrValue[$this->getDataContainer()->getName()]))
			{
				$arrValue[$this->getDataContainer()->getName()] = array();
			}

			$arrValue[$this->getDataContainer()->getName()]['offset'] = $intOffset;
			$arrValue[$this->getDataContainer()->getName()]['amount'] = $intAmount;
		}
		else
		{
			unset($arrValue[$this->getDataContainer()->getName()]);
		}

		$this->getInputProvider()->setPersistentValue('limit', $arrValue);
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

		$objCurrentDataProvider = $this
			->getPanel()
			->getContainer()
			->getDataContainer()
			->getDataProvider();

		$objParentDataProvider = $this
			->getPanel()
			->getContainer()
			->getDataContainer()
			->getDataProvider('parent');

		if ($objParentDataProvider)
		{
			$objParent = $objParentDataProvider->fetch($objParentDataProvider->getEmptyConfig()->setId($idParent));

			$objCondition = $this->getDataContainer()->getEnvironment()->getDataDefinition()->getChildCondition(
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

				$objConfig->setFilter(
					array(
						array(
							'operation' => 'AND',
							'children'    => $arrFilter,
						)
					)
				);
			}
		}

		return $objConfig;
	}

	/**
	 * {@inheritDoc}
	 */
	public function initialize(ConfigInterface $objConfig, PanelElementInterface $objElement = null)
	{
		if (is_null($objElement))
		{
			$objTempConfig = $this->getOtherConfig($objConfig);

			$this->addParentFilter(
				$this->getDataContainer()->getEnvironment()->getInputProvider()->getParameter('id'),
				$objTempConfig
			);

			$mixTotal = $this
				->getPanel()
				->getContainer()
				->getDataContainer()
				->getDataProvider()
				->fetchAll($objTempConfig->setIdOnly(true));

			$this->intTotal = is_array($mixTotal) ? count($mixTotal) : (is_object($mixTotal) ? $mixTotal->length() : 0);
			$offset = 0;
			// TODO: we need to determine the perPage some better way.
			$amount = $GLOBALS['TL_CONFIG']['resultsPerPage'];

			$input = $this->getInputProvider();
			if ($this->getPanel()->getContainer()->updateValues() && $input->hasValue('tl_limit'))
			{
				$limit  = explode(',', $input->getValue('tl_limit'));
				$offset = $limit[0];
				$amount = $limit[1];

				$this->setPersistent($offset, $amount);
			}

			$persistent = $this->getPersistent();
			if ($persistent)
			{
				$offset = $persistent['offset'];
				$amount = $persistent['amount'];

				// Hotfix the offset - we also might want to store it persistent.
				// Another way would be to always stick on the "last" page when we hit the upper limit.
				if ($offset > $this->intTotal)
				{
					$offset = 0;
				}
			}

			if (!is_null($offset))
			{
				$this->setOffset($offset);
				$this->setAmount($amount);
			}
		}

		$objConfig->setStart($this->getOffset());
		$objConfig->setAmount($this->getAmount());
	}

	/**
	 * {@inheritDoc}
	 */
	public function render(ViewTemplateInterface $objTemplate)
	{
		$arrOptions = array
		(
			array
			(
				'value'      => 'tl_limit',
				'attributes' => '',
				'content'    => $GLOBALS['TL_LANG']['MSC']['filterRecords']
			)
		);

		$options_total = ceil($this->intTotal / $GLOBALS['TL_CONFIG']['resultsPerPage']);

		for ($i = 0; $i < $options_total; $i++)
		{
			$first       = ($i * $GLOBALS['TL_CONFIG']['resultsPerPage']);
			$this_limit  = $first . ',' . $GLOBALS['TL_CONFIG']['resultsPerPage'];
			$upper_limit = ($first + $GLOBALS['TL_CONFIG']['resultsPerPage']);

			if ($upper_limit > $this->intTotal)
			{
				$upper_limit = $this->intTotal;
			}

			$arrOptions[] = array
			(
				'value'      => $this_limit,
				'attributes' => ($this->getOffset() == $first) ? ' selected="selected"' : '',
				'content'    => ($first + 1) . ' - ' . $upper_limit
			);
		}

		if ($this->intTotal > $GLOBALS['TL_CONFIG']['resultsPerPage'])
		{
			$arrOptions[] = array
			(
				'value'      => 'all',
				'attributes' => (($this->getOffset() == 0) && ($this->getAmount() == $this->intTotal)) ? ' selected="selected"' : '',
				'content'    => $GLOBALS['TL_LANG']['MSC']['filterAll']
			);
		}

		$objTemplate->options = $arrOptions;

		return $this;
	}

	/**
	 * Set the offset to use in this element.
	 *
	 * @param int $intOffset
	 *
	 * @return PanelElementInterface
	 */
	public function setOffset($intOffset)
	{
		$this->intOffset = $intOffset;

		return $this;
	}

	/**
	 * Get the offset to use in this element.
	 *
	 * @return int
	 */
	public function getOffset()
	{
		return $this->intOffset;
	}

	/**
	 * Set the Amount to use in this element.
	 *
	 * @param int $intAmount
	 *
	 * @return PanelElementInterface
	 */
	public function setAmount($intAmount)
	{
		$this->intAmount = $intAmount;
	}

	/**
	 * Get the amount to use in this element.
	 *
	 * @return int
	 */
	public function getAmount()
	{
		return $this->intAmount;
	}
}
