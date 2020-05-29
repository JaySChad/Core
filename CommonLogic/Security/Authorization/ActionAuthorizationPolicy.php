<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\Interfaces\Security\PermissionInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\PolicyTargetDataType;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Factories\PermissionFactory;
use exface\Core\CommonLogic\Selectors\UserRoleSelector;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Interfaces\Selectors\FileSelectorInterface;
use exface\Core\CommonLogic\Selectors\UiPageGroupSelector;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Selectors\ActionSelectorInterface;

/**
 * Policy for access to actions.
 * 
 * @author Andrej Kabachnik
 *
 */
class ActionAuthorizationPolicy implements AuthorizationPolicyInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $name = '';
    
    private $userRoleSelector = null;
    
    private $actionSelector = null;
    
    private $metaObjectSelector = null;
    
    private $pageGroupSelector = null;
    
    private $conditionUxon = null;
    
    private $effect = null;
    
    private $actionTriggerPageKnown = null;
    
    private $excludeActionSelectors = [];
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $name
     * @param PolicyEffectDataType $effect
     * @param array $targets
     * @param UxonObject $conditionUxon
     */
    public function __construct(WorkbenchInterface $workbench, string $name, PolicyEffectDataType $effect, array $targets, UxonObject $conditionUxon = null)
    {
        $this->workbench = $workbench;
        $this->name = $name;
        if ($str = $targets[PolicyTargetDataType::USER_ROLE]) {
            $this->userRoleSelector = new UserRoleSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::ACTION]) {
            $this->actionSelector =  new ActionSelector($this->workbench, $str);
        }
        if ($str = $targets[PolicyTargetDataType::META_OBJECT]) {
            $this->metaObjectSelector = new MetaObjectSelector($this->workbench, $str);
        }        
        if ($str = $targets[PolicyTargetDataType::PAGE_GROUP]) {
            $this->pageGroupSelector = new UiPageGroupSelector($this->workbench, $str);
        }
        
        $this->conditionUxon = $conditionUxon;
        $this->importUxonObject($conditionUxon);
        
        $this->effect = $effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->conditionUxon ?? new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::authorize()
     */
    public function authorize(UserImpersonationInterface $userOrToken = null, ActionInterface $action = null, TaskInterface $task = null): PermissionInterface
    {
        $applied = false;
        try {
            if ($action === null) {
                throw new InvalidArgumentException('Cannot evalute action access policy: no action provided!');
            }
            
            // Match action
            if (($selector = $this->actionSelector) !== null) {
                switch(true) {
                    case $selector->isFilepath():
                        $selectorClassPath = StringDataType::substringBefore($selector->toString(), '.' . FileSelectorInterface::PHP_FILE_EXTENSION);
                        $actionClassPath = FilePathDataType::normalize(get_class($action));
                        $applied = $selectorClassPath === $actionClassPath;
                        break;
                    case $selector->isClassname():
                        $applied = trim(get_class($action), "\\") === trim($selector->toString(), "\\");
                        break;
                    case $selector->isAlias():
                        $applied = $action->getAliasWithNamespace() === $selector->toString();
                        break;
                }
                if ($applied === false) {
                    return PermissionFactory::createNotApplicable($this);
                }
            } else {
                $applied = true;
            }
            
            // Match user
            if ($userOrToken instanceof AuthenticationTokenInterface) {
                $user = $this->workbench->getSecurity()->getUser($userOrToken);
            } else {
                $user = $userOrToken;
            }
            if ($this->userRoleSelector !== null && $user->hasRole($this->userRoleSelector) === false) {
                return PermissionFactory::createNotApplicable($this);
            } else {
                $applied = true;
            }
            
            // See if trigger page must be known
            if ($this->getActionTriggerPageKnownOption() !== null) {
                $needToKnow = $this->getActionTriggerPageKnownOption();
                $triggerKnown = $this->isActionTriggerPageKnown($action, $task);
                switch (true) {
                    case $needToKnow === true && $triggerKnown === false:
                    case $needToKnow === false && $triggerKnown === true:
                        return PermissionFactory::createNotApplicable($this);
                    default:
                        $applied = true;
                }
            }
            
            // Match meta object
            if ($this->metaObjectSelector !== null) {
                $object = $action->getMetaObject();
                if ($object === null || $object->is($this->metaObjectSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            // Match page
            if ($this->pageGroupSelector !== null) {
                if ($action !== null && $action->isDefinedInWidget()) {
                    $page = $action->getWidgetDefinedIn()->getPage();
                } elseif ($task !== null && $task->isTriggeredOnPage()) {
                    $page = $task->getPageTriggeredOn();
                } else {
                    $page = null;
                }
                
                if ($page->isInGroup($this->pageGroupSelector) === false) {
                    return PermissionFactory::createNotApplicable($this);
                } else {
                    $applied = true;
                }
            } else {
                $applied = true;
            }
            
            foreach ($this->getExcludeActions() as $selector) {
                if ($action->isExactly($selector)) {
                    return PermissionFactory::createNotApplicable($this);
                }
            }
            
            if ($applied === false) {
                return PermissionFactory::createNotApplicable($this);
            }
        } catch (\Throwable $e) {
            return PermissionFactory::createIndeterminate($e, $this->getEffect(), $this);
        }
        
        // If all targets are applicable, the permission is the effect of this condition.
        return PermissionFactory::createFromPolicyEffect($this->getEffect(), $this);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getEffect()
     */
    public function getEffect() : PolicyEffectDataType
    {
        return $this->effect;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPolicyInterface::getName()
     */
    public function getName() : ?string
    {
        return $this->name;
    }
    
    protected function setActionTriggerPageKnown(bool $trueOrFalse) : ActionAuthorizationPolicy
    {
        $this->actionTriggerPageKnown = $trueOrFalse;
        return $this;
    }
    
    protected function getActionTriggerPageKnownOption() : ?bool
    {
        return $this->actionTriggerPageKnown;
    }
    
    protected function isActionTriggerPageKnown(ActionInterface $action, TaskInterface $task = null) : bool
    {
        if ($task && $task->isTriggeredOnPage()) {
            return true;
        }
        
        if ($action->isDefinedInWidget()) {
            return true;
        }
        
        return false;
    }
    
    protected function setExcludeActions(UxonObject $excludes) : ActionAuthorizationPolicy
    {
        foreach ($excludes->getPropertiesAll() as $selectorString) {
            $this->excludeActionSelectors[] = new ActionSelector($this->workbench, $selectorString);
        }
        return $this;
    }
    
    /**
     * 
     * @return ActionSelectorInterface[]
     */
    protected function getExcludeActions() : array
    {
        return $this->excludeActionSelectors;
    }
}