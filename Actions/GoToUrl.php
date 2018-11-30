<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iShowUrl;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Actions\ActionRuntimeError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\BooleanDataType;

/**
 * This action opens a URL for a given object instance.
 * The URL can contain placeholders, that will
 * ber replaced by attribute values of the instance. This is usefull in tables, where a URL needs
 * to be opened for a specific row. Any value from that row can be passed to the URL vial placeholder [#column_id#]
 *
 * @author Andrej Kabachnik
 *        
 */
class GoToUrl extends AbstractAction implements iShowUrl
{

    private $url = null;

    private $open_in_new_window = false;

    /**
     * @var boolean
     */
    private $urlencode_placeholders = true;

    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(1);
        $this->setIcon(Icons::EXTERNAL_LINK);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowUrl::getUrl()
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Defines the URL to navigate to.
     * 
     * @uxon-property url
     * @uxon-type string
     * 
     * @see \exface\Core\Interfaces\Actions\iShowUrl::setUrl()
     */
    public function setUrl($value)
    {
        $this->url = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $vars = array();
        $vals = array();
        foreach ($this->getInputDataSheet($task)->getRow(0) as $var => $val) {
            $vars[] = '[#' . $var . '#]';
            $vals[] = urlencode($val);
        }
        
        $result = str_replace($vars, $vals, $this->getUrl());
        $result = filter_var($result, FILTER_SANITIZE_STRING);
        if (substr($result, 0, 4) !== 'http') {
            $result = $this->getWorkbench()->getCMS()->buildUrlToFile($result);
        }
        
        $result = ResultFactory::createUriResult($task, $result);
        $result->setMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.GOTOURL.SUCCESS'));
        if ($this->getOpenInNewWindow()) {
            $result->setOpenInNewWindow(true);
        }
        
        return $result;
    }

    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool
    {
        return $this->open_in_new_window;
    }

    /**
     * Set to TRUE to make the page open in a new browser window or tab (depending on the browser).
     * 
     * @uxon-property open_in_new_window
     * @uxon-type bool
     * 
     * @param bool|string $value
     * @return \exface\Core\Actions\GoToUrl
     */
    public function setOpenInNewWindow($value) : GoToUrl
    {
        $this->open_in_new_window = BooleanDataType::cast($value);
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getUrlencodePlaceholders() : bool
    {
        return $this->urlencode_placeholders;
    }

    /**
     * 
     * Makes all placeholders get encoded and thus URL-safe if set to TRUE (default).
     * 
     * Use FALSE if placeholders are ment to use as-is (e.g. the URL itself is a placeholder)
     * 
     * @uxon-property urlencode_placeholders
     * @uxon-type boolean
     * 
     * @param bool|string $value
     * @return \exface\Core\Actions\GoToUrl
     */
    public function setUrlencodePlaceholders($value)
    {
        $this->urlencode_placeholders = BooleanDataType::cast($value);
        return $this;
    }
    
    public function buildUrlFromDataSheet(DataSheetInterface $data_sheet, $row_nr = 0)
    {
        $url = $this->getUrl();
        $placeholders = StringDataType::findPlaceholders($url);
        foreach ($placeholders as $ph){
            if ($col = $data_sheet->getColumns()->getByExpression($ph)){
                $url = str_replace('[#' . $ph . '#]', $col->getCellValue($row_nr), $url);
            } else {
                throw new ActionRuntimeError($this, 'No value found for placeholder "' . $ph . '" in URL! Make sure, the input data sheet has a corresponding column!');
            }
        }
        return $url;
    }
}
?>