<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\ActionFactory;
use exface\Core\Factories\TaskFactory;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Events\DataTransactionEventInterface;
use exface\Core\Exceptions\Actions\ActionObjectNotSpecifiedError;
use exface\Core\Interfaces\Events\TaskEventInterface;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\CommonLogic\DataSheets\DataColumn;

/**
 * Attachable to DataSheetEvents (exface.Core.DataSheet.*), calls any action.
 * 
 * For this behavior to work, it has to be attached to an object in the metamodel. The event-
 * alias and the action have to be configured in the behavior configuration.
 * 
 * ## Examples
 * 
 * ### Call an ection every time an instance of this object is created
 * 
 * ```
 * {
 *  "event_alias": "exface.Core.DataSheet.OnBeforeCreateData",
 *  "action": {
 *      "alias": "..."
 *  }
 * }
 * 
 * ```
 * 
 * ### Log data every time the state of a document changes to "30"
 * 
 * ```
 *  {
 *      "event_alias": "exface.Core.DataSheet.OnBeforeUpdateData",
 *      "only_if_attributes_change": ["STATE"],
 *      "only_if_data_matches_conditions": {
 *          "operator": "AND",
 *          "conditions": [
 *              {"expression": "STATE", "comparator": "==", "value": 30}
 *          ]
 *      },
 *      "action": {
 *          "alias": "exface.Core.CreateData",
 *          "object_alias": "my.App.DOC_STATE_LOG",
 *          "input_mapper": {
 *              "from_object_alias": "my.App.DOC",
 *              "to_object_alias": my.App.DOC_STATE_LOG",
 *              "column_to_column_mappings": [
 *                  {"from": "...", "to": "..."},
 *              ]
 *          }
 *      }
 *  }
 * 
 * ```
 * 
 * @author SFL
 * @author Andrej Kabachnik
 *
 */
class CallActionBehavior extends AbstractBehavior
{
    private $eventAlias = null;

    private $action = null;
    
    private $actionConfig = null;
    
    private $priority = null;
    
    private $onlyIfAttributesChange = [];
    
    private $onlyIfDataMatchesConditionGroupUxon = null;
    
    private $ignoreDataSheets = [];
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        // Register the change-check listener first to make sure it is called before the 
        // call-action listener even if both listen the OnBeforeUpdateData event
        if ($this->hasRestrictionOnAttributeChange()) {
            $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckChange'], $this->getPriority());
        }
        
        $this->getWorkbench()->eventManager()->addListener($this->getEventAlias(), [$this, 'onEventCallAction'], $this->getPriority());
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener($this->getEventAlias(), [$this, 'onEventCallAction'], $this->getPriority());
        
        if ($this->hasRestrictionOnAttributeChange()) {
            $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onBeforeUpdateCheckChange']);
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('event_alias', $this->getEventAlias());
        $uxon->setProperty('action', $this->getAction()->exportUxonObject());
        if ($this->getPriority() !== null) {
            $uxon->setProperty('priority', $this->getPriority());
        }
        if ($this->hasRestrictionOnAttributeChange()) {
            $uxon->setProperty('only_if_attributes_change', new UxonObject($this->getOnlyIfAttributesChange()));
        }
        if ($this->hasRestrictionOnAttributeChange()) {
            $uxon->setProperty('only_if_data_matches_conditions', $this->onlyIfDataMatchesConditionGroupUxon);
        }
        return $uxon;
    }

    /**
     * 
     * @return string
     */
    protected function getEventAlias() : string
    {
        return $this->eventAlias;
    }

    /**
     * Alias of the event, that should trigger the action.
     * 
     * Technically, any type of event selector will do - e.g.: 
     * - `exface.Core.DataSheet.OnBeforeCreateData`
     * - `\exface\Core\Events\DataSheet\OnBeforeCreateData`
     * - OnBeforeCreateData::class (in PHP)
     * 
     * @uxon-property event_alias
     * @uxon-type metamodel:event
     * @uxon-required true
     * 
     * @param string $aliasWithNamespace
     * @return CallActionBehavior
     */
    protected function setEventAlias(string $aliasWithNamespace) : CallActionBehavior
    {
        $this->eventAlias = $aliasWithNamespace;
        return $this;
    }

    /**
     * 
     * @return ActionInterface
     */
    protected function getAction()
    {
        if ($this->action === null) {
            $this->action = ActionFactory::createFromUxon($this->getWorkbench(), UxonObject::fromAnything($this->actionConfig));
            try {
                $this->action->getMetaObject();
            } catch (ActionObjectNotSpecifiedError $e) {
                $this->action->setMetaObject($this->getObject());
            }
        }
        return $this->action;
    }

    /**
     * Sets the action which is executed upon the configured event.
     * 
     * @uxon-property action
     * @uxon-type \exface\Core\CommonLogic\AbstractAction
     * @uxon-template {"alias": ""}
     * @uxon-required true
     * 
     * @param UxonObject|string $action
     * @return BehaviorInterface
     */
    protected function setAction($action)
    {
        $this->actionConfig = $action;
        return $this;
    }

    /**
     * Executes the action if applicable
     * 
     * @param EventInterface $event
     * @return void
     */
    public function onEventCallAction(EventInterface $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        if (! $event instanceof DataSheetEventInterface) {
            throw new BehaviorConfigurationError($this->getObject(), 'The CallActionBehavior cannot be triggered by event "' . $event->getAliasWithNamespace() . '": currently only data sheet events supported!');
        }
        
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->is($this->getObject())) {
            return;
        }
        
        if (in_array($data_sheet, $this->ignoreDataSheets)) {
            $this->getWorkbench()->getLogger()->debug('Behavior ' . $this->getAlias() . ' skipped for object ' . $this->getObject()->__toString() . ' because of `only_if_attributes_change`', [], $data_sheet);
            return;
        }
        
        if ($this->hasRestrictionConditions()) {
            $data_sheet = $data_sheet->extract($this->getOnlyIfDataMatchesConditions());
            if ($data_sheet->isEmpty()) {
                $this->getWorkbench()->getLogger()->debug('Behavior ' . $this->getAlias() . ' skipped for object ' . $this->getObject()->__toString() . ' because of `only_if_data_matches_conditions`', [], $data_sheet);
                return;
            }
        }
        
        if ($action = $this->getAction()) {
            if ($event instanceof TaskEventInterface) {
                $task = $event->getTask();
                $task->setInputData($data_sheet);
            } else {
                // We never have an input widget here, so tell the action it won't get one
                // and let it deal with it.
                $action->setInputTriggerWidgetRequired(false);
                $task = TaskFactory::createFromDataSheet($data_sheet);
            }
            if ($event instanceof DataTransactionEventInterface) {
                $action->handle($task, $event->getTransaction());
            } else {
                $action->handle($task);
            }
        }
    }
    
    /**
     * Checks if any of the `only_if_attribtues_change` attributes are about to change
     * 
     * @param OnBeforeUpdateDataEvent $event
     * @return void
     */
    public function onBeforeUpdateCheckChange(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $event->getDataSheet()->getMetaObject()->is($this->getObject())) {
            return;
        }
        
        $ignore = true;
        foreach ($this->getOnlyIfAttributesChange() as $attrAlias) {
            if ($event->willChangeColumn(DataColumn::sanitizeColumnName($attrAlias))) {
                $ignore = false;
                break;
            }
        }
        if ($ignore === true) {
            $this->ignoreDataSheets[] = $event->getDataSheet();
        }
    }
    
    /**
     *
     * @return int|NULL
     */
    protected function getPriority() : ?int
    {
        return $this->priority;
    }
    
    /**
     * Event handlers with higher priority will be executed first!
     * 
     * @uxon-property priority
     * @uxon-type integer
     * 
     * @param int $value
     * @return CallActionBehavior
     */
    public function setPriority(int $value) : CallActionBehavior
    {
        $this->priority = $value;
        return $this;
    }
    
    protected function getOnlyIfAttributesChange() : array
    {
        return $this->onlyIfAttributesChange ?? [];
    }
    
    /**
     * Only call the action if any of these attributes change (list of aliases)
     * 
     * @uxon-property only_if_attributes_change
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param UxonObject $value
     * @return CallActionBehavior
     */
    protected function setOnlyIfAttributesChange(UxonObject $value) : CallActionBehavior
    {
        $this->onlyIfAttributesChange = $value->toArray();
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasRestrictionOnAttributeChange() : bool
    {
        return $this->onlyIfAttributesChange !== null;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasRestrictionConditions() : bool
    {
        return $this->onlyIfDataMatchesConditionGroupUxon !== null;
    }
    
    /**
     * 
     * @return ConditionGroupInterface|NULL
     */
    protected function getOnlyIfDataMatchesConditions() : ?ConditionGroupInterface
    {
        if ($this->onlyIfDataMatchesConditionGroupUxon === null) {
            return null;
        }
        return ConditionGroupFactory::createFromUxon($this->getWorkbench(), $this->onlyIfDataMatchesConditionGroupUxon, $this->getObject());
    }
    
    /**
     * Only call the action if it's input data would match these conditions
     * 
     * @uxon-property only_if_data_matches_conditions
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "=","value": ""}]}
     * 
     * @param UxonObject $uxon
     * @return CallActionBehavior
     */
    protected function setOnlyIfDataMatchesConditions(UxonObject $uxon) : CallActionBehavior
    {
        $this->onlyIfDataMatchesConditionGroupUxon = $uxon;
        return $this;
    }
}