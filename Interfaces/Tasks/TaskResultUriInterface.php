<?php
namespace exface\Core\Interfaces\Tasks;

use Psr\Http\Message\UriInterface;

/**
 * Interfaces for task results of actions, that produce widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface TaskResultUriInterface extends TaskResultInterface
{    
    /**
     * 
     * @return UriInterface
     */
    public function getUri() : UriInterface;
    
    /**
     * 
     * @param UriInterface $uri
     * @return TaskResultUriInterface
     */
    public function setUri(UriInterface $uri) : TaskResultUriInterface;
    
    /**
     * 
     * @return bool
     */
    public function hasUri() : bool;    
    
}