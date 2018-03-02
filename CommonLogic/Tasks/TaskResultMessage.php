<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;

/**
 * Generic task result implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultMessage implements TaskResultInterface
{
    private $task = null;
    
    private $isDataModified = false;
    
    private $isContextModified = false;
    
    private $isUndoable = false;
    
    private $message = null;
    
    private $workbench = null;
    
    private $responseCode = 200;
    
    /**
     * 
     * @param TaskInterface $task
     */
    public function __construct(TaskInterface $task, $message = '')
    {
        $this->task = $task;
        $this->workbench = $task->getWorkbench();
        if ($message !== '') {
            $this->setMessage($message);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::setDataModified()
     */
    public function setDataModified(bool $value): TaskResultInterface
    {
        $this->isDataModified = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::isUndoable()
     */
    public function isUndoable(): bool
    {
        return $this->isUndoable;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::setUndoable()
     */
    public function setUndoable(bool $trueOrFalse): TaskResultInterface
    {
        $this->isUndoable = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::isDataModified()
     */
    public function isDataModified(): bool
    {
        return $this->isDataModified;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::getMessage()
     */
    public function getMessage(): string
    {
        // If there is a custom result message text defined, use it instead of the autogenerated message
        if (is_null($this->message)) {
            $message = '';
        } else {
            $message = $this->message;
            $placeholders = $this->getWorkbench()->utils()->findPlaceholdersInString($message);
            if ($placeholders) {
                $message = '';
                foreach ($this->getResultDataSheet()->getRows() as $row) {
                    $message_line = $this->getResultMessageText();
                    foreach ($placeholders as $ph) {
                        $message_line = str_replace('[#' . $ph . '#]', $row[$ph], $message_line);
                    }
                    $message .= ($message ? "\n" : '') . $message_line;
                }
            }
        }
        
        return $message;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::getTask()
     */
    public function getTask(): TaskInterface
    {
        return $this->task;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::setMessage()
     */
    public function setMessage(string $string): TaskResultInterface
    {
        $this->message = $string;
        return $this;
    }
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\TaskResultInterface::setReponseCode()
     */
    public function setReponseCode(int $number) : TaskResultInterface
    {
        $this->responseCode = $number;
        return $this;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }
    
    public function isContextModified(): bool
    {
        return $this->isContextModified;
    }

    public function setContextModified(bool $trueOrFalse): TaskResultInterface
    {
        $this->isContextModified = $trueOrFalse;
        return $this;
    }


}