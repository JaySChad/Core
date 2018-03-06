<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Actions\iExportData;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Factories\TaskResultFactory;
use GuzzleHttp\Psr7\Uri;

/**
 * This action exports the raw data received by a widget as a file for download.
 * 
 * The data is exported exactly as the input widget of this action would receive
 * it in the current template: e.g. as JSON for a typical DataTable widget in
 * an AJAX template.
 * 
 * ExportData is a good base to create other exporting actions, that would
 * encode the data differently:
 * @see ExportCSV
 * @see ExportXLSX
 * 
 * @author Andrej Kabachnik
 *
 */
class ExportData extends ReadData implements iExportData
{

    private $download = true;
    
    private $filename = null;
    
    private $mimeType = null;
    
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::DOWNLOAD);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ReadData::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {        
        $dataSheet = $this->readData($task);
        $url = $this->export($dataSheet);
        $uri = new Uri($url);
        $message = 'Download ready. If not id does not start automatically, click <a href="' . $url . '">here</a>.';
        $result = TaskResultFactory::createFileResult($task, $uri);
        $result->setMessage($message);
        return $result;
    }
    
    protected function readData(TaskInterface $task) : DataSheetInterface
    {
        $dataSheet = $this->getInputDataSheet($task);
        // Make sure, the input data has all the columns required for the widget
        // we export from. Generally this will not be the case, because the
        // widget calling the action is a button and it normally does not know
        // which columns to export.
        if ($this->isDefinedInWidget()) {
            $widget = $this->getWidgetDefinedIn();
        } elseif ($task->isTriggeredOnPage()) {
            $widget = $task->getWidgetTriggeredBy();
        }
        if (isset($widget) && $this->getWidgetDefinedIn()->is('Button')){
            $this->getWidgetDefinedIn()->getInputWidget()->prepareDataSheetToRead($dataSheet);
        }
        
        $dataSheet->removeRows()->dataRead();
        return $dataSheet;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getDownload()
     */
    public function getDownload()
    {
        return $this->download;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setDownload()
     */
    public function setDownload($true_or_false)
    {
        $this->download = BooleanDataType::cast($true_or_false);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getFilename()
     */
    public function getFilename()
    {
        if (is_null($this->filename)){
            return 'export_' . date('Y-d-m_his', time());
        }
        return $this->filename;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setFilename()
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }
    
    protected function export(DataSheetInterface $dataSheet)
    {
        $elem = $this->getApp()->getWorkbench()->ui()->getTemplate()->getElement($this->getWidgetDefinedIn());
        $output = $elem->prepareData($dataSheet);
        $contents = $this->getApp()->getWorkbench()->ui()->getTemplate()->encodeData($output, false);
        If (is_null($this->getMimeType())){
            // TODO get the mime type from the template somehow
            $this->setMimeType('application/json');
        }
        return $this->createDownload($contents);
    }
    
    /**
     * Creates a downloadable file with the given contents and returns it's URL
     * 
     * @param string $contents
     * @return string
     */
    protected function createDownload($contents){
        $filemanager = $this->getWorkbench()->filemanager();
        $pathname = Filemanager::pathJoin([$filemanager->getPathToCacheFolder(), $this->getFilename() . '.' . $this->getFileExtension()]);
        
        file_put_contents($pathname, $contents);
        
        /*header('Content-Description: File Transfer');
         header('Content-Type: text/csv');
         header('Content-Disposition: attachment; filename=data.csv');
         header('Content-Transfer-Encoding: binary');
         header('Expires: 0');
         header('Cache-Control: must-revalidate');
         header('Pragma: public');
         header('Content-Length: ' . filesize($tmpName));*/
        $url = $this->getWorkbench()->getCMS()->createLinkToFile($pathname);
        
        return ($url);
    }
    
    public function getFileExtension(){
        switch ($this->getMimeType()){
            case 'application/json': return 'json';
            case 'text/xml': return 'xml';
            case 'text/csv': return 'csv';
            case 'text/plain': return 'txt';
            case 'application/vnd.openxmlformats-officedocument. spreadsheetml.sheet': return 'xlsx';
            // TODO add more from https://wiki.selfhtml.org/wiki/MIME-Type/%C3%9Cbersicht#X
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::getMimeType()
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iExportData::setMimeType()
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
        return $this;
    }
 
    public function getResultOutput(){
        return '';
    }
    
}
?>