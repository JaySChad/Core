<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Exceptions\Widgets\WidgetIdConflictError;
use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\EventFactory;
use exface\Core\Exceptions\Widgets\WidgetNotFoundError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\UiPageFactory;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\UiPageNotPartOfAppError;
use Ramsey\Uuid\Uuid;

/**
 * This is the default implementation of the UiPageInterface.
 * 
 * The first widget without a parent added to the page is concidered to be the
 * main root widget.
 * 
 * Widgets get cached in an internal array.
 * 
 * @see UiPageInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class UiPage implements UiPageInterface
{
    
    use ImportUxonObjectTrait;

    const WIDGET_ID_SEPARATOR = '_';

    const WIDGET_ID_SPACE_SEPARATOR = '.';

    private $widgets = array();

    private $template = null;

    private $ui = null;

    private $widget_root = null;

    private $context_bar = null;

    private $appUidOrAlias = null;

    private $updateable = true;

    private $menuParentPageAlias = null;

    private $menuParentPageSelector = null;

    private $menuDefaultPosition = null;

    private $menuIndex = 0;

    private $menuVisible = true;

    private $id = null;

    private $name = null;

    private $shortDescription = null;

    private $replacesPageAlias = null;

    private $contents = null;

    private $contents_uxon = null;

    private $aliasWithNamespace = null;

    private $dirty = false;

    /**
     *
     * @deprecated use UiPageFactory::create() instead!
     * @param UiManagerInterface $ui
     * @param string $alias
     * @param string $uid
     * @param string $appUidOrAlias
     */
    public function __construct(UiManagerInterface $ui, $alias = null, $uid = null, $appUidOrAlias = null)
    {
        $this->ui = $ui;
        $this->setAliasWithNamespace($alias);
        $this->setId($uid);
        $this->setAppUidOrAlias($appUidOrAlias);
    }

    /**
     *
     * @param WidgetInterface $widget            
     * @throws WidgetIdConflictError
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public function addWidget(WidgetInterface $widget)
    {
        $widget->setIdAutogenerated($this->generateId($widget));
        if ($widget->getIdSpecified() && $widget->getIdSpecified() != $this->sanitizeId($widget->getIdSpecified())) {
            throw new WidgetIdConflictError($widget, 'Explicitly specified id "' . $widget->getIdSpecified() . '" for widget "' . $widget->getWidgetType() . '" not unique on page "' . $this->getId() . '": please specify a unique id for the widget in the UXON description of the page!');
            return $this;
        }
        
        // Remember the first widget added automatically as the root widget of the page
        if (empty($this->widgets) && ! $widget->is('ContextBar')) {
            $this->widget_root = $widget;
        }
        
        $this->widgets[$widget->getId()] = $widget;
        return $this;
    }

    /**
     *
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function getWidgetRoot()
    {
        if ($this->isDirty()) {
            $this->regenerateFromContents();
        }
        return $this->widget_root;
    }

    /**
     * Initializes all widgets from the contents of the page
     * 
     * @return UiPage
     */
    protected function regenerateFromContents()
    {
        $this->removeAllWidgets();
        if (! $this->getContentsUxon()->isEmpty()) {
            WidgetFactory::createFromUxon($this, $this->getContentsUxon());
        }
        return $this;
    }

    /**
     * Returns the UXON representation of the contents
     * 
     * @return UxonObject
     */
    protected function getContentsUxon()
    {
        if (is_null($this->contents_uxon)) {
            if (! is_null($this->contents)) {
                $contents = $this->getContents();
                if (substr($contents, 0, 1) == '{' && substr($contents, - 1) == '}') {
                    $uxon = UxonObject::fromAnything($contents);
                } else {
                    $uxon = new UxonObject();
                }
            } else {
                $uxon = new UxonObject();
            }
        } else {
            $uxon = $this->contents_uxon;
        }
        
        return $uxon;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getWidget()
     */
    public function getWidget($id, WidgetInterface $parent = null)
    {
        if ($this->isDirty()) {
            $this->regenerateFromContents();
            $this->dirty = false;
        }
        
        if (is_null($id) || $id == '') {
            return $this->getWidgetRoot();
        }
        
        // First check to see, if the widget id is already in the widget list. If so, return the corresponding widget.
        // Otherwise look throgh the entire tree to make sure, even subwidgets with late binding can be found (= that is
        // those, that are created if a certain property of another widget is accessed.
        if ($widget = $this->widgets[$id]) {
            // FIXME Check if one of the ancestors of the widget really is the given parent. Although this should always
            // be the case, but better doublecheck ist.
            return $widget;
        }
        
        // If the parent is null, look under the root widget
        // FIXME this makes a non-parent lookup in pages with multiple roots impossible.
        if (is_null($parent)) {
            if (StringDataType::startsWith($id . static::WIDGET_ID_SEPARATOR, $this->getContextBar()->getId()) || StringDataType::startsWith($id . static::WIDGET_ID_SPACE_SEPARATOR, $this->getContextBar()->getId())) {
                $parent = $this->getContextBar();
            } else {
                // If the page is empty, no widget can be found ;) ...except the widget, that are always there
                if ($this->isEmpty()) {
                    throw new WidgetNotFoundError('Widget "' . $id . '" not found in page "' . $this->getAliasWithNamespace() . '": page empty!');
                }
                $parent = $this->getWidgetRoot();
            }
        }
        
        if ($id_space_length = strpos($id, static::WIDGET_ID_SPACE_SEPARATOR)) {
            $id_space = substr($id, 0, $id_space_length);
            $id = substr($id, $id_space_length + 1);
            return $this->getWidgetFromIdSpace($id, $id_space, $parent);
        } else {
            return $this->getWidgetFromIdSpace($id, '', $parent);
        }
    }

    /**
     * This method contains the searching algorithm for finding a widget in a parent by it's id.
     * 
     * One of the main goals is to decrease the number of widgets, that need to be instantiated
     * by including the parent id in the child id, thus creating id-paths. The trouble is though,
     * that user-defined ids can be hidden anywhere in the widget tree, so we can't assume, that
     * all ids adhere to the path-idea. Another challange is the search for widgets, that where
     * repositioned after receiving the id (thus, the id does not match the path anymore).
     * 
     * By default, the algorithm will try to determine, if the given id is a path. If so, it will
     * only search within widgets along that path (only these widgets will get instantiated). Only
     * for non-path ids or in the case, where a path-like id cannot be found, a full search of the
     * tree will be performed.
     * 
     * Here is a typical page with a data table as an example. The ids are shortened to two letters
     * and a number, so DT is a DataTable, TC is DataTableConfigurator, BT is a button, etc. Our
     * Table has a configurator, one toolbar (DT_TB) with a single button group with 3 buttons and
     * one column group (DT_CG) with 4 columns. The second button and the third colum have user-
     * defined ids. All other ids are autogenerated.
     * 
     * DT - DT_TC - DT_TC_TA - DT_TC_TA_FI  - DT_TC_TA_FI_IN
     *   |                  +- DT_TC_TA_FI2 - DT_TC_CA_FI2_CT
     *   |
     *   +- DT_TB - DT_TB_BG  - DT_TB_BG_BT
     *   |       |           +- mybtn ------ - mybtn_DI ------ - mybtn_DI_IN
     *   |       |           |                                +- mybtn_DI_IN2
     *   |       |           |                                +- mybtn_DI_IN3
     *   |       |           |
     *   |       |           +- DT_TB_BG_BT2 - DT_TB_BG_BT2_DI - DT_TB_BG_BT2_DT - DT_TB_BG_BT2_DT_CO
     *   |       |                                                              +- DT_TB_BG_BT2_DT_CO2
     *   |       |                                                              +- DT_TB_BG_BT2_DT_CO3
     *   |       +- DT_TB_BG2 - DT_BT_BG2_BT
     *   |                   +- DT_BT_BG_BT3
     *   |       
     *   |                              
     *   |
     *   +- DT_CG - DT_CG_CO
     *           +- DT_CG_CO2
     *           +- mycol
     *           +- DT_CG_CO3
     *           
     * Here are some examples, of how the searching works:
     * 
     * (1) Searching for the Filter-ComboTable with id DT_TC_CA_FI2_CT. The id will get identified as
     * a path because it starts with DT, which is the id of the root widget. Iterating over the children
     * of DT will immediately yield a path-match on DT_TC. The search continues there and so on until
     * a full match is found.
     * 
     * (2) Searching for the button DT_TB_BG_BT works similarly, but DT_TC does not fit the path, so
     * the search among the children of DT continues without traversing up the DT_TC-branch.
     * 
     * (3) Searching for mybtn results in searching the DT_TC-branch completely and the beginning of
     * the DT_TB-branch.
     * 
     * (4) The DataTable within the Dialog DT_TB_BG_BT2_DI was moved there after being instantiated
     * for the button DT_TB_BG_BT2 directly, so it has the id DT_TB_BG_BT2_TB instead of one with
     * a full path (DT_TB_BG_BT2_DI_TB). The path-search will work upto the button and will result
     * in a miss while iterating over the button's children. In this case, a fallback search through
     * all widgets in the DT_TB_BG_BT2-branch is initiated, which will lead to the desired result.
     * The same happens if a button is instantiated programmatically with the DT as parent and is
     * getting moved to the default button group of the default toolbar when being added to the table.
     * 
     * (5) In the worst case, widgets can get moved somewhere far away (outside of their paren) like 
     * button DT_BT_BG_BT3 which was created in the first button group, but was moved to the second one 
     * for some reason. This happens really rarely though.
     *  
     * IDEA Buttons showing widgets will currently load their widget and treat it as a child even if
     * it is linked from another page and not defined explicity. Perhaps, we can optimize here a little
     * and make iShowWidget::getWidget() only return widgets from the current page, while offering
     * a second way to get the widget via iShowWidget::getWidgetLink()->...
     * 
     * @param string $id
     * @param string $id_space
     * @param WidgetInterface $parent
     * @param boolean $use_id_path
     * 
     * @throws WidgetNotFoundError if no matching widget was found
     * 
     * @return WidgetInterface
     */
    private function getWidgetFromIdSpace($id, $id_space, WidgetInterface $parent, $use_id_path = true)
    {
        $id_with_namespace = static::addIdSpace($id_space, $id);
        if ($widget = $this->widgets[$id_with_namespace]) {
            // FIXME Check if one of the ancestors of the widget really is the given parent. Although this should always
            // be the case, but better doublecheck ist.
            return $widget;
        }
        
        if ($parent->getId() === $id) {
            return $parent;
        }
        
        if (StringDataType::startsWith($id_space, $parent->getId() . self::WIDGET_ID_SEPARATOR)) {
            $id_space_root = $this->getWidget($id_space, $parent);
            return $this->getWidgetFromIdSpace($id, $id_space, $id_space_root);
        }
        
        $id_is_path = false;
        if (StringDataType::startsWith($id_with_namespace, $parent->getId() . self::WIDGET_ID_SEPARATOR)) {
            $id_is_path = true;
        }
        
        if ($parent instanceof iHaveChildren) {
            foreach ($parent->getChildren() as $child) {
                $child_id = $child->getId();
                if ($child_id == $id_with_namespace) {
                    return $child;
                } else {
                    if (! $use_id_path || ! $id_is_path || StringDataType::startsWith($id_with_namespace, $child_id . self::WIDGET_ID_SEPARATOR)) {
                        // If we are looking for a non-path id or the path includes the id of the child, look within the child
                        try {
                            // Note, the child may deside itself, whe
                            return $this->getWidgetFromIdSpace($id, $id_space, $child);
                        } catch (WidgetNotFoundError $e) {
                            // Catching the error means, we did not find the widget in this branch.
                            
                            if ($id_is_path) {
                                // If we had a path-match, this probably means, that the widget was moved (see example 4 in
                                // the method-docblock). In this case, the path in it's id does not match the real path anymore.
                                // However, since this mostly happens when widgets get moved around within their parent, we
                                // try a deep search within the child, that matched the id-path, first. This will help in
                                // example 4 too, as the widget was just moved one level up the tree.
                                try {
                                    // Setting the parameter $use_id_path to false makes the children search among their
                                    // children even if those don't match the path. In example 4 we would get to this line
                                    // after searching the child DT_TB_BG_BT2 of widget DT_TB_BG. DT_TB_BG_BT2 itself
                                    // seems to match the path, but none of it's direct children do. Now we tell the
                                    // DT_TB_BG_BT2 to treat the id as a non-path. This will make it pass the search
                                    // to every child of DT_TB_BG_BT2. These will search regularly though, so stargin
                                    // from DT_TB_BG_BT2_DT the path-idea will work again.
                                    return $this->getWidgetFromIdSpace($id, $id_space, $child, false);
                                } catch (WidgetNotFoundError $ed) {
                                    // If the deep-search fails too, we know, the widget was moved somewehere else (example 5)
                                    // or the really does not exist. In this case, we stop looking through children (no other
                                    // children will match the path anyway).
                                    break;
                                }
                            } else {
                                // For non-path ids just continue with the next child as we do not know, where the id might be.
                                continue;
                            }
                        }
                    } elseif ($id_is_path) {
                        // If the id is a path, but did not include the child id, continue with the next child
                        continue;
                    }
                }
            }
            
            // At this point, we know, the widget was not found by the regular search methods.
            // There are two possibilities left:
            // 1) The id seemed to be a path and worked upto the current parent widget
            // 2) The id is not a path
            // TODO We still need some kind of fallback for example 5 here!
        }
        
        throw new WidgetNotFoundError('Widget "' . $id . '" not found in id space "' . $id_space . '" within parent "' . $parent->getId() . '" on page "' . $this->getAliasWithNamespace() . '"!');
        
        return;
    }

    private static function addIdSpace($id_space, $id)
    {
        return (is_null($id_space) || $id_space === '' ? '' : $id_space . static::WIDGET_ID_SPACE_SEPARATOR) . $id;
    }

    /**
     * Generates an unique id for the given widget.
     * If the widget has an id already, this is merely sanitized.
     *
     * @param WidgetInterface $widget            
     * @return string
     */
    protected function generateId(WidgetInterface $widget)
    {
        if (! $id = $widget->getId()) {
            if ($widget->getParent()) {
                $id = $widget->getParent()->getId() . self::WIDGET_ID_SEPARATOR;
            }
            $id .= $widget->getWidgetType();
        }
        return $this->sanitizeId($id);
    }

    /**
     * Makes sure, the given widget id is unique in this page.
     * If not, the id gets a numeric index, which makes it unique.
     * Thus, the returned value is guaranteed to be unique!
     *
     * @param string $string            
     * @return string
     */
    protected function sanitizeId($string)
    {
        if ($this->widgets[$string]) {
            $index = substr($string, - 2);
            if (is_numeric($index)) {
                $index_new = str_pad(intval($index + 1), 2, 0, STR_PAD_LEFT);
                $string = substr($string, 0, - 2) . $index_new;
            } else {
                $string .= '02';
            }
            
            return $this->sanitizeId($string);
        }
        return $string;
    }

    /**
     *
     * @return \exface\Core\Interfaces\TemplateInterface
     */
    public function getTemplate()
    {
        if (is_null($this->template)) {
            // FIXME need a method to get the template from the CMS page here somehow. It should probably become a method of the CMS-connector
            // The mapping between CMS-templates and ExFace-templates needs to move to a config variable of the CMS-connector app!
        }
        return $this->template;
    }

    /**
     *
     * @param TemplateInterface $template            
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    protected function setTemplate(TemplateInterface $template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     *
     * @param string $widget_type            
     * @param WidgetInterface $parent_widget            
     * @param string $widget_id            
     * @return WidgetInterface
     */
    public function createWidget($widget_type, WidgetInterface $parent_widget = null, UxonObject $uxon = null)
    {
        if ($uxon) {
            $uxon->setProperty('widget_type', $widget_type);
            $widget = WidgetFactory::createFromUxon($this, $uxon, $parent_widget);
        } else {
            $widget = WidgetFactory::create($this, $widget_type, $parent_widget);
        }
        return $widget;
    }

    /**
     *
     * @param string $widget_id            
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public function removeWidgetById($widget_id)
    {
        unset($this->widgets[$widget_id]);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Model\UiPageInterface::removeWidget()
     */
    public function removeWidget(WidgetInterface $widget, $remove_children_too = true)
    {
        if ($remove_children_too) {
            foreach ($this->widgets as $cached_widget) {
                if ($cached_widget->getParent() === $widget) {
                    $this->removeWidget($cached_widget, true);
                }
            }
        }
        $result = $this->removeWidgetById($widget->getId());
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createWidgetEvent($widget, 'Remove.After'));
        
        return $result;
    }

    /**
     * 
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    public function removeAllWidgets()
    {
        foreach ($this->widgets as $cached_widget) {
            $this->removeWidgetById($cached_widget->getId());
            $this->getWorkbench()->eventManager()->dispatch(EventFactory::createWidgetEvent($cached_widget, 'Remove.After'));
        }
        $this->widgets = [];
        $this->widget_root = null;
        
        return $this;
    }

    /**
     *
     * @return UiManagerInterface
     */
    public function getUi()
    {
        return $this->ui;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->getUi()->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getWidgetIdSeparator()
     */
    public function getWidgetIdSeparator()
    {
        return self::WIDGET_ID_SEPARATOR;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getWidgetIdSpaceSeparator()
     */
    public function getWidgetIdSpaceSeparator()
    {
        return self::WIDGET_ID_SPACE_SEPARATOR;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::isEmpty()
     */
    public function isEmpty()
    {
        return $this->getWidgetRoot() ? false : true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getContextBar()
     */
    public function getContextBar()
    {
        if (is_null($this->context_bar)) {
            $this->context_bar = WidgetFactory::create($this, 'ContextBar');
        }
        return $this->context_bar;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getApp()
     */
    public function getApp()
    {
        if ($this->appUidOrAlias) {
            return $this->getWorkbench()->getApp($this->appUidOrAlias);
        } else {
            throw new UiPageNotPartOfAppError('The page "' . $this->getAliasWithNamespace() . '" is not part of any app!');
        }
    }

    /**
     * Sets the app UID or alias, this page belongs to.
     * 
     * @param string $appUidOrAlias
     * @return UiPageInterface
     */
    protected function setAppUidOrAlias($appUidOrAlias)
    {
        $this->appUidOrAlias = $appUidOrAlias;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::isUpdateable()
     */
    public function isUpdateable()
    {
        return $this->updateable;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setUpdateable()
     */
    public function setUpdateable($true_or_false)
    {
        if (! is_null($true_or_false)) {
            // BooleanDataType::cast() ist sehr restriktiv darin einen Wert als true zurueckzugeben,
            // im Zweifelsfall wird false zurueckgegeben. Updatetable sollte aber im Zweifelsfall
            // eher true sein.
            $this->updateable = BooleanDataType::cast($true_or_false);
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getMenuParentPageAlias()
     */
    public function getMenuParentPageAlias()
    {
        if (is_null($this->menuParentPageAlias) && ! is_null($this->menuParentPageSelector)) {
            $this->menuParentPageAlias = $this->getMenuParentPage()->getAliasWithNamespace();
        }
        return $this->menuParentPageAlias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setMenuParentPageAlias()
     */
    public function setMenuParentPageAlias($menuParentPageAlias)
    {
        $this->menuParentPageAlias = $menuParentPageAlias;
        $this->menuParentPageSelector = null;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getMenuParentPage()
     */
    public function getMenuParentPage()
    {
        return $this->getWorkbench()->ui()->getPage($this->getMenuParentPageSelector());
    }

    /**
     * Returns the selector (id or alias) for the parent page in the main menu or NULL if no parent defined.
     * 
     * @return string|null
     */
    protected function getMenuParentPageSelector()
    {
        if (is_null($this->menuParentPageSelector) && ! is_null($this->menuParentPageAlias)) {
            return $this->menuParentPageAlias;
        }
        return $this->menuParentPageSelector;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setMenuParentPageSelector()
     */
    public function setMenuParentPageSelector($id_or_alias)
    {
        $this->menuParentPageSelector = $id_or_alias;
        $this->menuParentPageAlias = null;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getMenuDefaultPosition()
     */
    public function getMenuDefaultPosition()
    {
        return $this->menuDefaultPosition;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setMenuDefaultPosition()
     */
    public function setMenuDefaultPosition($menuDefaultPosition)
    {
        $this->menuDefaultPosition = $menuDefaultPosition;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getMenuIndex()
     */
    public function getMenuIndex()
    {
        return $this->menuIndex;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setMenuIndex()
     */
    public function setMenuIndex($number)
    {
        $this->menuIndex = NumberDataType::cast($number);
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getMenuPosition()
     */
    public function getMenuPosition()
    {
        return $this->getMenuParentPageAlias() . ':' . $this->getMenuIndex();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::isMoved()
     */
    public function isMoved()
    {
        return strcasecmp($this->getMenuPosition(), $this->getMenuDefaultPosition()) != 0;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getMenuVisible()
     */
    public function getMenuVisible()
    {
        return $this->menuVisible;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setMenuVisible()
     */
    public function setMenuVisible($menuVisible)
    {
        if (! is_null($menuVisible)) {
            $this->menuVisible = BooleanDataType::cast($menuVisible);
        }
        return $this;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getId()
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Overwrites the unique id of the page
     * 
     * @param string $uid
     * @return UiPageInterface
     */
    protected function setId($uid)
    {
        $this->id = $uid;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setName()
     */
    public function setName($string)
    {
        $this->name = $string;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getShortDescription()
     */
    public function getShortDescription()
    {
        return $this->shortDescription;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setShortDescription()
     */
    public function setShortDescription($string)
    {
        $this->shortDescription = $string;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getReplacesPageAlias()
     */
    public function getReplacesPageAlias()
    {
        return $this->replacesPageAlias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setReplacesPageAlias()
     */
    public function setReplacesPageAlias($alias_with_namespace)
    {
        $this->replacesPageAlias = $alias_with_namespace;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::getContents()
     */
    public function getContents()
    {
        if (is_null($this->contents) && ! is_null($this->contents_uxon)) {
            $this->contents = $this->contents_uxon->toJson();
        }
        
        return $this->contents;
    }

    /**
     * Returns TRUE if the contents of the page was modified since the last time widgets were generated.
     * 
     * Run regenerateWidgetsFromContents() to make the page not dirty.
     * 
     * @return boolean
     */
    protected function isDirty()
    {
        return $this->dirty;
    }

    /**
     * Marks this page as dirty: all widgets will be removed immediately and will get regenerated the next 
     * time the user requests a widget.
     * 
     * @return \exface\Core\CommonLogic\Model\UiPage
     */
    private function setDirty()
    {
        $this->removeAllWidgets();
        $this->dirty = true;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::setContents()
     */
    public function setContents($contents)
    {
        $this->setDirty();
        
        if (is_string($contents)) {
            $this->contents = trim($contents);
        } elseif ($contents instanceof UxonObject) {
            $this->contents_uxon = $contents;
        } else {
            throw new InvalidArgumentException('Cannot set contents from ' . gettype($contents) . ': expecting string or UxonObject!');
        }
        
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     */
    public function getAlias()
    {
        if (($sepPos = strrpos($this->getAliasWithNamespace(), NameResolver::NAMESPACE_SEPARATOR)) !== false) {
            $alias = substr($this->getAliasWithNamespace(), $sepPos + 1);
        } else {
            $alias = $this->getAliasWithNamespace();
        }
        return $alias;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->aliasWithNamespace;
    }

    /**
     * Sets the alias of the page.
     * 
     * @param string $aliasWithNamespace
     * @return UiPageInterface
     */
    protected function setAliasWithNamespace($aliasWithNamespace)
    {
        $this->aliasWithNamespace = $aliasWithNamespace;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getNamespace()
     */
    public function getNamespace()
    {
        if (($sepPos = strrpos($this->getAliasWithNamespace(), NameResolver::NAMESPACE_SEPARATOR)) !== false) {
            $namespace = substr($this->getAliasWithNamespace(), 0, $sepPos);
        } else {
            $namespace = '';
        }
        return $namespace;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        /** @var UxonObject $uxon */
        $uxon = $this->getWorkbench()->createUxonObject();
        $uxon->setProperty('id', $this->getId());
        $uxon->setProperty('alias_with_namespace', $this->getAliasWithNamespace());
        $uxon->setProperty('menu_parent_page_alias', $this->getMenuParentPageAlias());
        $uxon->setProperty('menu_index', $this->getMenuIndex());
        $uxon->setProperty('menu_visible', $this->getMenuVisible());
        $uxon->setProperty('name', $this->getName());
        $uxon->setProperty('short_description', $this->getShortDescription());
        $uxon->setProperty('replaces_page_alias', $this->getReplacesPageAlias());
        
        $contents = trim($this->getContents());
        if (! $contents) {
            // contents == null
            $contents = '';
        } else {
            if (substr($contents, 0, 1) == '{' && substr($contents, - 1) == '}') {
                // contents == UxonObject
                $contents = UxonObject::fromJson($contents);
                if ($contents->isEmpty()) {
                    $contents = '';
                }
            } else {
                // contents == string
                $contents = $this->getContents();
            }
        }
        $uxon->setProperty('contents', $contents);
        
        return $uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::copy()
     */
    public function copy($page_alias = null, $page_uid = null)
    {
        $copy = UiPageFactory::createFromUxon($this->getUi(), $this->exportUxonObject());
        if ($page_uid) {
            $copy->setId($page_uid);
        }
        if ($page_alias) {
            $copy->setAliasWithNamespace($page_alias);
        }
        // Copy internal properties, that do not get exported to UXON
        $copy->setAppUidOrAlias($this->appUidOrAlias);
        $copy->setMenuDefaultPosition($this->getMenuDefaultPosition());
        return $copy;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::is()
     */
    public function is($page_or_id_or_alias)
    {
        if ($this->isExactly($page_or_id_or_alias)) {
            // Die uebergebene Seite ist genau diese Seite.
            return true;
        }
        
        if ($page_or_id_or_alias instanceof UiPageInterface) {
            $page_or_id_or_alias = $page_or_id_or_alias->getAliasWithNamespace();
        }
        
        // Ersetzt die uebergebene Seite eine andere Seite, koennte es diese Seite sein (auch
        // ueber eine Kette von Ersetzungen).
        $replacedPage = $this->getWorkbench()->getCMS()->loadPage($this->getAliasWithNamespace());
        if ($replacedPage->isExactly($page_or_id_or_alias)) {
            return true;
        }
        
        // Ersetzt diese Seite eine andere Seite, koennte es die uebergebene Seite sein (auch
        // ueber eine Kette von Ersetzungen).
        // Dies kann hier aber nicht so einfach ueberprueft werden, da es bei fehlerhaften Links
        // sonst zu Fehlern beim Laden der Seite kommt.
        
        // Leider waeren hier fuer eine exakte Pruefung bei laengeren Ketten von Ersetzungen auf
        // beiden Seiten eine exponentiell zunehmende Anzahl von Vergleichen zu tun.
        
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::isExactly()
     */
    public function isExactly($page_or_id_or_alias)
    {
        if ($page_or_id_or_alias instanceof UiPageInterface) {
            return $this->compareTo($page_or_id_or_alias->getId(), $page_or_id_or_alias->getAliasWithNamespace());
        } else {
            return $this->compareTo($page_or_id_or_alias, $page_or_id_or_alias);
        }
    }

    protected function compareTo($id, $alias)
    {
        if ($this->getId() && $id && strcasecmp($this->getId(), $id) == 0) {
            return true;
        } elseif ($this->getAliasWithNamespace() && $alias && strcasecmp($this->getAliasWithNamespace(), $alias) == 0) {
            return true;
        }
        
        return false;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::equals()
     */
    public function equals(UiPageInterface $page)
    {
        return $this->getId() == $page->getId() && $this->getAliasWithNamespace() == $page->getAliasWithNamespace() && $this->getMenuVisible() == $page->getMenuVisible() && $this->getName() == $page->getName() && $this->getShortDescription() == $page->getShortDescription() && $this->getReplacesPageAlias() == $page->getReplacesPageAlias() && $this->getContents() == $page->getContents();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::generateUid()
     */
    public static function generateUid()
    {
        return '0x' . Uuid::uuid1()->getHex();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\UiPageInterface::generateAlias()
     */
    public static function generateAlias($prefix)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $aliasLength = 10;
        $alias = '';
        for ($i = 0; $i < $aliasLength; $i ++) {
            $alias .= $characters[mt_rand(0, $charactersLength - 1)];
        }
        return $prefix . $alias;
    }
}

?>
