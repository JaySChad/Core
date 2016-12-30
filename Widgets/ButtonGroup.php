<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Widgets\iHaveIcon;
use exface\Core\CommonLogic\UxonObject;

/**
 * A group of button widgets with a mutual input widget. 
 * 
 * Depending on the template, a ButtonGroup can be displayed as a list of buttons or even transformed to a menu.
 * 
 * @author Andrej Kabachnik
 *
 */
class ButtonGroup extends AbstractWidget implements iHaveButtons, iHaveIcon {
	private $buttons =  array();
	private $icon_name = null;
	private $input_widget = null;
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::get_buttons()
	 */
	public function get_buttons() {
		return $this->buttons;
	}
	
	/**
	 * Defines the contained buttons via array of button definitions.
	 * 
	 * @uxon-property buttons
	 * @uxon-type Button[]
	 * 
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::set_buttons()
	 */
	public function set_buttons(array $buttons_array) {
		if (!is_array($buttons_array)) return false;
		foreach ($buttons_array as $b){
			$button = $this->get_page()->create_widget('Button', $this, UxonObject::from_anything($b));
			$this->add_button($button);
		}
	}
	
	/**
	 * Adds a button to the group
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::add_button()
	 */
	public function add_button(Button $button_widget){
		$button_widget->set_parent($this);
		$button_widget->set_input_widget($this->get_input_widget());
		$button_widget->set_input_widget($this->get_input_widget());
		$this->buttons[] = $button_widget;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Interfaces\Widgets\iHaveIcon::get_icon_name()
	 */
	public function get_icon_name() {
		return $this->icon_name;
	}
	
	/**
	 * Sets the icon for the button group. Use one of the generic icon names or any notation supported by the template.
	 * 
	 * @uxon-property icon_name
	 * @uxon-type string
	 * 
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iHaveIcon::set_icon_name()
	 */
	public function set_icon_name($value) {
		$this->icon_name = $value;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children() {
		return $this->get_buttons();
	}
	
	/**
	 * Returns the input widget for buttons in this group. That is the widget, that holds the data,
	 * the button's actions are supposed to be performed upon. Since button groups can be nested, we
	 * need to travel up all the group hierarchy to the first parent, which is not a button group and
	 * thus contains all the buttons (or would contain them if there were no groups).
	 */
	protected function get_input_widget(){
		if (!$this->input_widget){
			do {
				$parent = $this->get_parent();
			} while ($parent instanceof ButtonGroup);
			$this->input_widget = $parent;
		}
		return $this->input_widget;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\Widgets\iHaveButtons::has_buttons()
	 */
	public function has_buttons() {
		if (count($this->buttons)) return true;
		else return false;
	}
}
?>