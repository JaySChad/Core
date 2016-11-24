<?php namespace exface\Core\Interfaces;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\CommonLogic\Model\RelationPath;

interface WidgetInterface extends ExfaceClassInterface {
	
	/**
	 * Loads data from a standard object (stdClass) into any widget using setter functions.
	 * E.g. calls $this->set_id($source->id) for every property of the source object. Thus the behaviour of this
	 * function like error handling, input checks, etc. can easily be customized by programming good 
	 * setters.
	 * @param \stdClass $source
	 */
	function import_uxon_object(\stdClass $source);
	
	/**
	 * Prefills the widget with values of a data sheet
	 * @param \exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet
	 */
	function prefill(DataSheetInterface $data_sheet);
	
	/**
	 * Adds attributes, filters, etc. to a given data sheet, so that it can be used to fill the widget with data
	 * @param DataSheet $data_sheet
	 * @return DataSheetInterface
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null);
	
	/**
	 * Adds attributes, filters, etc. to a given data sheet, so that it can be used to prefill the widget
	 * @param DataSheet $data_sheet
	 * @return DataSheetInterface
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null);
	
	/**
	 * Sets the widget caption/title
	 * @param string $caption
	 */
	public function set_caption($caption);
	
	/**
	 * Returns the UID of the base meta object for this widget
	 * @return string
	 */
	public function get_meta_object_id();
	
	/**
	 * 
	 * @param string $id
	 */
	public function set_meta_object_id($id);	
	
	/**
	 * Returns the widget id specified for this widget explicitly (e.g. in the UXON description). Returns NULL if there was no id
	 * explicitly specified! Use get_id() instead, if you just need the currently valid widget id.
	 * @return string
	 */
	public function get_id_specified();
	
	/**
	 * Returns the widget id generated automatically for this widget. This is not neccesserily the actual widget id - if an id was
	 * specified explicitly (e.g. in the UXON description), it will be used instead. 
	 * Use get_id() instead, if you just need the currently valid widget id.
	 * @return string
	 */
	public function get_id_autogenerated();
	
	/**
	 * Sets the autogenerated id for this widget
	 * @param string $value
	 * @return \exface\Core\Interfaces\WidgetInterface
	 */
	public function set_id_autogenerated($value);
	
	/**
	 * Specifies the id of the widget explicitly, overriding any previos values. The given id must be unique
	 * within the page. It will not be modified automatically in any way.
	 * @param string $value
	 * @return WidgetInterface
	 */
	public function set_id($value);
	
	/**
	 * Returns true if current widget is a container, false otherwise
	 * @return boolean
	 */
	public function is_container();
	
	/**
	 * Returns the child widget matching the given id or FALSE if no child with this id was found
	 * @param string $widget_id
	 * @return AbstractWidget|boolean
	 */
	public function find_child_recursive($widget_id);
	
	/**
	 * @return string
	 */
	public function get_caption();
	
	/**
	 * Returns TRUE if the caption is supposed to be hidden
	 * @return boolean
	 */
	public function get_hide_caption();
	
	/**
	 * @param unknown $value
	 * @return WidgetInterface
	 */
	public function set_hide_caption($value); 
	
	/**
	 * 
	 * @throws \exface\Core\Exceptions\UiWidgetException
	 * @return \exface\Core\CommonLogic\Model\Object
	 */
	public function get_meta_object();
	
	/**
	 * Sets the given object as the new base object for this widget
	 * @param Object $object
	 */
	public function set_meta_object(Object $object);
	
	/**
	 * Returns the id of this widget
	 * @return string
	 */
	public function get_id();
	
	/**
	 * Returns the widget type (e.g. DataTable)
	 * @return string
	 */
	public function get_widget_type();
	
	/**
	 * @return boolean
	 */
	public function is_disabled();
	
	/**
	 * 
	 * @param boolean $value
	 */
	public function set_disabled($value);
	
	/**
	 * Returns a dimension object representing the height of the widget. 
	 * @return WidgetDimension
	 */
	public function get_width();
	
	/**
	 * Sets the width of the widget. The width may be specified in relative ExFace units (in this case, the value is numeric) 
	 * or in any unit compatible with the current template (in this case, the value is alphanumeric because the unit must be
	 * specified directltly).
	 * @param float|string $value
	 * @return WidgetInterface
	 */
	public function set_width($value);

	/**
	 * Returns a dimension object representing the height of the widget. 
	 * @return WidgetDimension
	 */
	public function get_height();
	
	/**
	 * Sets the height of the widget. The height may be specified in relative ExFace units (in this case, the value is numeric) 
	 * or in any unit compatible with the current template (in this case, the value is alphanumeric because the unit must be
	 * specified directltly).
	 * @param float|string $value
	 * @return WidgetInterface
	 */
	public function set_height($value);
	
	/**
	 * 
	 * @param string $qualified_alias_with_namespace
	 */
	public function set_object_alias($qualified_alias_with_namespace);
	
	/**
	 * Returns the relation path from the object of the parent widget to the object of this widget. If both widgets are based on the
	 * same object or no valid path can be found, an empty path will be returned.
	 * 
	 * @return RelationPath
	 */
	public function get_object_relation_path_from_parent();
	
	/**
	 * 
	 * @param string $string
	 */
	public function set_object_relation_path_from_parent($string);
	
	/**
	 * Returns the relation path from the object of this widget to the object of its parent widget. If both widgets are based on the
	 * same object or no valid path can be found, an empty path will be returned.
	 * 
	 * @return RelationPath
	 */
	public function get_object_relation_path_to_parent();
	
	/**
	 * 
	 * @param string $string
	 */
	public function set_object_relation_path_to_parent($string);
	
	/**
	 * Returns TRUE if the meta object of this widget was not set explicitly but inherited from it's parent and FALSE otherwise.
	 * @return boolean
	 */
	public function is_object_inherited_from_parent();
	
	/**
	 * Returns the parent widget
	 * @return WidgetInterface
	 */
	public function get_parent();
	
	/**
	 * Sets the parent widget
	 * @param WidgetInterface $widget
	 */
	public function set_parent(WidgetInterface &$widget); 
	
	/**
	 * Returns the UI manager
	 * @return \exface\Core\ui
	 */
	public function get_ui();

	/**
	 * @return string
	 */
	public function get_hint();
	
	/**
	 * @param string $value
	 */
	public function set_hint($value);
	
	/**
	 * @return boolean
	 */
	public function is_hidden();
	
	/**
	 * 
	 * @param boolean $value
	 */
	public function set_hidden($value);	
	
	/**
	 * Returns the current visibility option (one of the EXF_WIDGET_VISIBILITY_xxx constants)
	 * @return string
	 */
	public function get_visibility();
	
	/**
	 * Sets visibility of the widget (one of the EXF_WIDGET_VISIBILITY_xxx constants)
	 * @param string $value
	 * @throws \exface\Core\Exceptions\UiWidgetConfigException
	 */
	public function set_visibility($value);
	
	/**
	 * Returns the data sheet used to prefill the widget or null if the widget is not prefilled
	 * @return DataSheetInterface
	 */
	public function get_prefill_data();
	
	/**
	 * 
	 * @param DataSheetInterface $data_sheet
	 */
	public function set_prefill_data(DataSheetInterface $data_sheet);
	
	/**
	 * Checks if the widget implements the given interface (e.g. "iHaveChildren"), etc.
	 * @param string $interface_name
	 */
	public function implements_interface($interface_name);
	
	/**
	 * Returns TRUE if the widget is of the given widget type or extends from it and FALSE otherwise 
	 * (e.g. a DataTable would return TRUE for DataTable and Data)
	 * @param unknown $type
	 */
	public function is_of_type($type);
	
	/**
	 * Returns all actions callable from this widget or it's children as an array. Optional filters can be used to
	 * return only actions with a specified id (would be a single one in most cases) or qualified action alias (e.g. "exface.EditObjectDialog")
	 * @param string $qualified_action_alias
	 * @param string $action_type
	 * @return ActionInterface[]
	 */
	public function get_actions($qualified_action_alias = null, $action_id = null);	

	/**
	 * Returns aliases of attributes used to aggregate data
	 * TODO Not sure, if this should be a method of the abstract widget or a specific widget type. Can any widget have aggregators?
	 * @return array
	 */
	public function get_aggregations();
	
	/**
	 * Explicitly tells the widget to use the given data connection to fetch data (instead of the one specified on the base
	 * object's data source)
	 * @param string $value
	 */
	public function set_data_connection_alias($value);
	
	/**
	 * Creates a link to this widget and returns the corresponding model object
	 * @return WidgetLink
	 */
	public function create_widget_link();
	
	/**
	 * @return UiPageInterface
	 */
	public function get_page();
	
	/**
	 * @return string
	 */
	public function get_page_id();
	
}
?>