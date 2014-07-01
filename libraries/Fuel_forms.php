<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * FUEL CMS
 * http://www.getfuelcms.com
 *
 * An open source Content Management System based on the 
 * Codeigniter framework (http://codeigniter.com)
 */

// ------------------------------------------------------------------------

/**
 * Fuel Forms object 
 *
 * @package		FUEL CMS
 * @subpackage	Libraries
 * @category	Libraries
 */

// ------------------------------------------------------------------------

class Fuel_forms extends Fuel_advanced_module {
	
	public $name = "forms"; // the folder name of the module
	
	/**
	 * Constructor - Sets preferences
	 *
	 * The constructor can be passed an array of config values
	 */
	function __construct($params = array())
	{
		parent::__construct();

		$this->CI->load->library('validator');
		$this->CI->load->library('form_builder');
		$this->CI->load->module_helper(FORMS_FOLDER, 'forms');

		$this->initialize($params);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Initialize the backup object
	 *
	 * Accepts an associative array as input, containing preferences.
	 * Also will set the values in the config as properties of this object
	 *
	 * @access	public
	 * @param	array	config preferences
	 * @return	void
	 */	
	function initialize($params)
	{
		parent::initialize($params);
		$this->set_params($this->_config);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Creates and returns a single Fuel_form object
	 *
	 <code>$form = $this->fuel->forms->create('myform', array('fields' => array('name' => array('required' => TRUE))));</code>
	 * 
	 * @access	public
	 * @param	string	Name of the form
	 * @param	array	Initialization parameters
	 * @return	object
	 */	
	function create($name, $params = array())
	{
		$params['name'] = $name;
		$params['slug'] = url_title($name, '-', TRUE);

		$form = new Fuel_form();
		$form->initialize($params);
		if (isset($params['fields']))
		{
			$form->add_field($params['fields']);
		}
		return $form;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns a single Fuel_form object
	 *
	 <code>$form = $this->fuel->forms->get('myform');</code>
	 *
	 * @access	public
	 * @param	string	Name of the form to retrieve. Will first look in the database and then in the config file
	 * @return	object
	 */	
	function get($name)
	{
		$form = NULL;
		$params = array();

		// check the page mode to see if we can query the database
		if ($this->fuel->pages->mode() != 'views')
		{
			$forms_model = $this->model('forms');
			$forms_model->db()->where(array('name' => $name));
			$forms_model->db()->or_where(array('slug' => $name));
			if (is_int($name))
			{
				$forms_model->db()->or_where(array('id' => $name));	
			}
			$form_data = $forms_model->find_one();

			if (isset($form_data->id))
			{
				// prep values for initialization
				$params = $form_data->values(TRUE);
				$params['fields'] = $form_data->get_form_fields();
			}
		}

		// next check the configuration to see if there are any declared
		if (empty($form_data))
		{
			$forms = $this->config('forms');
			if (isset($forms[$name]))
			{
				$params = $forms[$name];
			}
			else
			{
				return FALSE;
			}
		}

		$form = $this->create($name, $params);
		return $form;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns a Fuel_block_layout object used in the CMS for creating fields in association with different field types (e.g. text, email, select... etc)
	 *
	 <code>$block_layout = $this->fuel->forms->field_layout('email');</code>
	 *
	 * @access	public
	 * @param	string	Name of the block layout to retrieve
	 * @return	object
	 */	
	function field_layout($layout)
	{
		return $this->fuel->layouts->get($layout, 'block');
	}

	// --------------------------------------------------------------------
	
	/**
	 * Magic method which allows you to make calls like $this->fuel->forms->render('test');
	 *
	 * @access	public
	 * @param	string	Method name
	 * @param	array	An array of arguments to pass to the method
	 * @return	mixed
	 */	
	function __call($method, $args)
	{
		if (isset($args[0]))
		{
			$name = $args[0];
			$form = $this->get($name);
			if (!isset($form->id))
			{
				$form = $this->create($name);
			}
			array_shift($args);
			return call_user_func_array(array($form, $method), $args);
		
		}
		throw new Exception('Invalid method call '.$$method);
	}
}



// ------------------------------------------------------------------------

/**
 * Fuel Form object 
 *
 */

//  ------------------------------------------------------------------------
class Fuel_form extends Fuel_base_library {

	protected $name = ''; // Name of the form (must be unique and is required)
	protected $slug = ''; // A slug value which can be passed to forms/{slug} for processing the form
	protected $save_entries = FALSE; // Determines whether to save the entries into the database
	protected $form_action = ''; // The URL in which to submit the form. If none is provided, one will be automatically created
	protected $anti_spam_method = array('method' => 'honeypot'); // The method to use to combat SPAM. Options are 'honeypot', 'equation', 'recaptcha' or 'akismet'.
	protected $submit_button_text = 'Submit'; // The text to display for the submit button
	protected $reset_button_text = ''; // The text to display for the reset button
	protected $form_display = 'auto'; // The method in which to options are 'auto', 'block', 'html'
	protected $block_view = ''; // The name of the block view file  (only necessary if form_display is set to "block")
	protected $block_view_module = ''; // The name of the module the block view belongs to (only necessary if form_display is set to "block")
	protected $form_html = ''; // The HTML to display (only necessary if form_display is set to "html")
	protected $javascript_submit = TRUE; // Determines whether to submit the form via AJAX
	protected $javascript_validate = TRUE; // Determines whether to use javascript to do front end validation before sending to the backend
	protected $javascript_waiting_message = 'Sending...'; // The message to display during the AJAX process
	protected $email_recipients = ''; // The recipients to recieve the email after form submission
	protected $email_subject = ''; // The subject line of the email being sent
	protected $email_message = ''; // The email message to send
	protected $after_submit_text = ''; // The text/HTML to display after the submission process
	protected $return_url = ''; // The return URL to use after the submission process. It will default to returning back to the page that submitted the form
	protected $validation = array(); // An array of extra validation rules to run during the submission process beyond 'required' and the rules setup by default for a field type (e.g. valid_email, valid_phone)
	protected $js = array(); // Additional javascript files to include for the rendering of the form
	protected $fields = array(); // The fields for the form. This is not required if you are using your own HTML in a block or HTML form_display view

	// --------------------------------------------------------------------
	
	/**
	 * Constructor
	 *
	 * Accepts an associative array as input, containing preferences (optional)
	 *
	 * @access	public
	 * @param	array	Config preferences
	 * @return	void
	 */	
	public function __construct($params = array())
	{
		parent::__construct();
		$this->initialize($params);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Initialize the backup object
	 *
	 * Accepts an associative array as input, containing preferences.
	 * Also will set the values in the config as properties of this object
	 *
	 * @access	public
	 * @param	array	config preferences
	 * @return	void
	 */	
	function initialize($params)
	{
		parent::initialize($params);

		// need to reset the validation object upon initialization since we are simply sharing the same one from $CI->validator
		$validator =& $this->get_validator();
		$validator->reset();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Renders the form for the front end.
	 *
	 <code>
	 $form = $this->fuel->forms->create('myform', array('fields' => array('name' => array('required' => TRUE))));
	 echo $form->render();

	 // OR, pass parameters directly to the "render" method
	 $form = $this->fuel->forms->create('myform');
	 echo $form->render(array('fields' => array('name' => array('required' => TRUE))));
	 </code>
	 * 
	 * @access	public
	 * @param	array	An array to modify object properties. If none are provided, it will use the current properties on the object (optional)
	 * @return	string 	Returns the rendered form
	 */	
	public function render($params = array())
	{
		$this->initialize($params);
		
		$this->CI->load->library('session');

		// process request
		if (!empty($_POST))
		{
			$this->process();
		}

		// catch any errors thrown back with flash data
		$validator =& $this->get_validator();
		if ($this->CI->session->flashdata('error'))
		{
			$validator->catch_errors($this->CI->session->flashdata('error'));
		}

		// initialize output string
		$output = $this->js_output();

		$this->CI->form_builder->load_custom_fields($this->get_custom_fields());

		$form_fields = $this->form_fields();

		// render from view or HTML
		if (strtolower($this->form_display) != 'auto' AND ($this->has_block_view() OR $this->has_form_html()))
		{
			$vars = $this->rendered_vars($form_fields);

			if ($this->form_display == 'block')
			{
				// use block view file
				$view = '_blocks/'.$this->block_view;
				$output .= $this->CI->load->module_view($this->block_view_module, $view, $vars, TRUE);
			}
			else
			{
				// use HTML from form
				$output .= $this->form_html;
			}
			$output = parse_template_syntax($output, $vars, TRUE);
		}
		else
		{
			$this->CI->form_builder->load_custom_fields($this->get_custom_fields());
			$this->CI->form_builder->set_validator($validator);
			$this->CI->form_builder->set_fields($form_fields);
			if ($this->has_submit_button_text())
			{
				$this->CI->form_builder->submit_value = $this->get_submit_button_text();
			}
			if ($this->has_reset_button_text())
			{
				$this->CI->form_builder->reset_value = $this->get_reset_button_text();
			}
			$posted = ($this->CI->session->flashdata('posted')) ? (array) $this->CI->session->flashdata('posted') : $_POST;
			$this->CI->form_builder->set_field_values($posted);

			$ajax_submit = ($this->is_javascript_submit()) ? ' data-ajax="true"' : '';
			$js_validate = ($this->is_javascript_validate()) ? ' data-validate="true"' : '';
			$js_waiting_message = ($this->is_javascript_validate()) ? ' data-ajax_message="'.rawurlencode($this->get_javascript_waiting_message()).'"' : '';
			$this->CI->form_builder->form_attrs = 'novalidate method="post" action="'.$this->get_form_action().'" class="form" id="'.$this->slug.'"'.$ajax_submit.$js_validate.$js_waiting_message;
			$this->CI->form_builder->display_errors = TRUE;
			$this->CI->form_builder->required_text = lang('forms_required');
			$output .= $this->CI->form_builder->render_divs();
		}
		

		if ($this->CI->session->flashdata('success'))
		{
			$output = $this->get_after_submit_text();
		}

		$output = $output;

		// create area for javascript callback
		return $output;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Adds a field to the form. This can also be done by passing the "fields" parameter when creating a a form. 
	 * 
	 <code>$form = $this->fuel->forms->create('myform', array('fields' => array('name' => array('required' => TRUE))));</code>
	 *
	 * @access	public
	 * @param	array	The name of the field to add
	 * @param	array	Form field parameters
	 * @return	object  Returns itself for method chaining
	 */	
	public function add_field($name, $params = array('type' => 'text'))
	{
		if (is_array($name))
		{
			foreach($name as $key => $value)
			{
				if (is_array($value))
				{
					if (empty($value['name']))
					{
						$value['name'] = $key;
					}
					if (empty($value['type']))
					{
						$value['type'] = 'text';
					}

					//$this->fields[$key] = $this->fuel->forms->create($key, $value);
					$this->fields[$key] = new Form_field($value);
				}
				elseif ($value instanceof Form_field)
				{
					//$this->fields[$key] = $value;
					$this->fields[$key] = $value;
				}
			}
		}
		else
		{
			if (is_array($params))
			{
				if (empty($params['name']))
				{
					$params['name'] = $name;
				}

				//$this->fields[$name] = $this->fuel->forms->create($name, $params);
				$this->fields[$name] = new Form_field($params);

			}
			elseif ($params instanceof Form_field)
			{
				$this->fields[$name] = $params;
			}
		}
		return $this;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Adds validation to a form.
	 * 
	 <code>
	 $validation = array('name', 'is_equal_to', 'Please make sure the passwords match', array('{password}', '{password2'));
	 $form = $this->fuel->forms->create('myform', array('fields' => array('name' => array('required' => TRUE))));
	 $form->add_validation($validation);
	 if ($form->validate())
	 {
		echo 'Is Valid!!!';
	 }
	 else
	 {
		echo 'Not Valid :-(';
	 }
	 </code>
	 *
	 * @access	public
	 * @param	mixed	Can be a string, an array like array('start_date', 'check_date', 'Please enter in a valid date.'), or an array of arrays
	 * @param	mixed	The function to validate with which must return either TRUE or FALSE. You can use the array($object, 'method') syntax for methods on object instances
	 * @param	string	The error message to display when the function returns FALSE
	 * @param	mixed	Can be a string or an array. You can use place holder to represent post data (e.g. {email});
	 * @return	object  Returns itself for method chaining
	 */	
	public function add_validation($name, $func = NULL, $msg = NULL, $params = array())
	{
		$validator =& $this->get_validator();

		if (is_array($name))
		{
			if (is_array(current($name)))
			{
				foreach($name as $key => $value)
				{
					$this->add_validation_rule($value);
				}
			}
			else
			{
				$this->add_validation_rule($name);
			}
		}
		elseif (is_string($name))
		{
			$this->add_validation_rule($name, $func, $msg, $params);

		}
		return $this;
	}
	

	// --------------------------------------------------------------------
	
	/**
	 * Adds a single validation rule to the validator object
	 * 
	 * @access	protected
	 * @return	object 	Returns the validator object 
	 */	
	protected function add_validation_rule($name, $func = NULL, $msg = NULL, $params = array())
	{
		$validator = $this->get_validator();
		$values = $this->CI->input->post();

		if (is_array($name))
		{
			$rule = $name;
		}
		else
		{
			$rule = array($name, $func, $msg, $params);
		}

		$key = $rule[0];
		$val = $this->CI->input->post($key);

		if (empty($rule[3]))
		{
			$rule[3] = (!empty($values[$key])) ? array($values[$key]) : array();
		} 
		else if (!is_array($rule[3])) 
		{
			$rule[3] = array($rule[3]);
		}
		
		// now replace any placeholders for values
		foreach($rule[3] as $r_key => $r_val) 
		{
			if (is_array($r_val))
			{
				foreach($r_val as $rv)
				{
					if (strpos($rv, '{') === 0)
					{
						$val_key = str_replace(array('{', '}'), '', $rv);
						if (isset($values[$val_key])) $rule[3][$r_key] = $values[$val_key];
					}
				}
			}
			else
			{
				if (strpos($r_val, '{') === 0)
				{
					$val_key = str_replace(array('{', '}'), '', $r_val);
					if (isset($values[$val_key])) $rule[3][$r_key] = $values[$val_key];
				}
			}
			
		}

		call_user_func_array(array($validator, 'add_rule'), $rule);
		return $validator;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Removes a validation rule from a form.
	 * 
	 <code>
	 $_POST['password'] = 'xx';
	 $_POST['password2'] = 'xxx';

	 $validation = array('name', 'is_equal_to', 'Please make sure the passwords match', array('{password}', '{password2'));
 	 $fields['password'] = array('type' => 'password', 'validation' => array($validation_rule));
	 $fields['password2'] = array('type' => 'password', 'label' => 'Password verfied');

	 $form = $this->fuel->forms->create('myform', array('fields' => array('name' => array('required' => TRUE))));

	 $form->add_validation($validation);
	 $validated = $form->validate(); // FALSE
	 $form->remove_validation('name');
	 $validated = $form->validate(); // TRUE
	 </code>
	 *
	 * @access	public
	 * @param 	string  Field to remove
	 * @param 	string  Key for rule (can have more then one rule for a field) (optional)
	 * @return	object  Returns itself for method chaining
	 */	
	public function remove_validation($key, $func = NULL)
	{
		$validator = $this->get_validator();
		$validator->remove_rule($key, $func);
		return $this;
	}
	// --------------------------------------------------------------------
	
	/**
	 * Processes the field which includes validation, submission to database (if submit_entries = TRUE) and emailing to recipients (if email_recipients isn't blank).
	 * 
	 <code>
	$form = $this->fuel->forms->get('myform');
	if ($form->process())
	{
		echo 'Success';
	}
	else
	{
		echo 'Failure';
	}
	 </code>
	 *
	 * @access	public
	 * @return	boolean  Returns whether the processing was successfull without any errors
	 */	
	public function process()
	{

		$msg = $this->get_email_message();

		// saved in the post so that it can be validated by post processors like Akismet
		$_POST['__email_message__'] = $msg;

		if ($this->validate())
		{

			if ($this->is_save_entries())
			{
				$posted = $this->clean_posted();
				$model =& $this->CI->fuel->forms->model('form_entries');
				$entry = $model->create();
				$entry->url = last_url();
				$entry->post = json_encode($posted);
				$entry->form_id = $this->id;
				$entry->remote_ip = $_SERVER['REMOTE_ADDR'];
				$entry->fill($posted);
				if (!$entry->save())
				{
					return FALSE;
				}
			}

			if (!$this->notify($msg))
			{
				return FALSE;
			}
			return TRUE;
		}
		return FALSE;		
	}

	// --------------------------------------------------------------------
	
	/**
	 * Validates the form before submission
	 * 
	 <code>
	$form = $this->fuel->forms->get('myform');
	if ($form->validate())
	{
		echo 'Is valid';
	}
	else
	{
		echo 'Invalid!!!';
	}
	// ... further processing
	 </code>
	 *
	 * @access	public
	 * @return	boolean  Returns TRUE/FALSE based on if the form validates or not. Is called during the "process" method as well
	 */	
	public function validate()
	{
		$this->CI->load->module_helper(FORMS_FOLDER, 'forms');

		// run post processing to validate custom fields
		$this->CI->form_builder->load_custom_fields($this->get_custom_fields());
		$this->CI->form_builder->set_fields($this->form_fields());
		$this->CI->form_builder->set_field_values($_POST);
		$this->CI->form_builder->post_process_field_values();

		$fields = $this->fields;

		$form_layouts = $this->CI->fuel->layouts->options_list(TRUE, 'Forms');
		$form_validators = array();
		foreach($form_layouts as $key => $layout)
		{
			$form_validators[$key] = $this->field_layout($key);
		}

		$validator = $this->get_validator();

		// loop through the $form variable to grab all the form fields marked as required to add validation rules
		foreach($fields as $f)
		{
			if (empty($f->name)) continue;
			$field = $form_validators[$f->type];

			if ($f->is_required())
			{
				$validator->add_rule($f->name, 'required', lang('forms_form_required', $f->label));
			}

			if (method_exists($field, 'frontend_validation'))
			{
				 // not necessary since this is the default, but doing it for good measure
				$field->set_validator($validator);

				// set front end validation rules
				$field->frontend_validation($f->name);
			}
		}

		// add blacklist validation
		$blacklist = $this->fuel->forms->config('blacklist');
		if (!empty($blacklist))
		{
			$validator->add_rule('ip_address', 'blacklisted', lang('forms_error_blacklisted'), $blacklist);	
		}

		// add any additional validation
		$this->run_other_validation();


		// VALIDATE!!!
		$validated = $validator->validate();

		if (!$validated)
		{
			$this->_add_error($validator->get_errors());
		}
		return $validated;

	}

	// --------------------------------------------------------------------
	
	/**
	 * Sends email notification to those specified in the email_recipients field. Is called within the process method as well.
	 * 
	 <code>
	$form = $this->fuel->forms->create('myform', array('email_recipients' => array('superman@krypton.com')));
	if ($form->notify())
	{
		echo 'Notified';
	}
	else
	{
		echo 'Failure in Notification';
	}
	 </code>
	 *
	 * @access	public
	 * @param   string   The message to send
	 * @return	boolean  Returns TRUE/FALSE based on if the form validates or not. Is called during the "process" method as well
	 */	
	public function notify($msg)
	{
		if ($this->has_email_recipients())
		{
			$this->CI->load->library('email');
			$email =& $this->CI->email;
			$forms =& $this->CI->fuel->forms;

			// send email
			$email->from($this->fuel->forms->config('email_from'));

			// Set the email subject
			$email->subject($this->fuel->forms->config('email_from'));

			// check config if we are in dev mode
			if (is_dev_mode())
			{
				$email->to($this->fuel->forms->config('test_email'));
			}
			else
			{
				// need to fill this out to work
				$email->to($this->email_recipients);
			}

			// Build the email content
			$email->message($msg);

			// let her rip
			if (!$email->send())
			{
				if (is_dev_mode())
				{
					echo '<pre>';
					print_r($email->print_debugger());
					echo '</pre>';
					exit();
				}
				$this->_add_error(lang('forms_error_sending_email'));
				return FALSE;
			}
		}
		return TRUE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the javascript files used for rendering the form. The render method will automatically call this method, however,
	 * it's at your disposal if you wish to render it in your own block outside of that process.
	 * 
	 <code>
	$form = $this->fuel->forms->create('myform', array('js' => array('myvalidation.js')));
	echo $form->js_output();
	 </code>
	 *
	 * @access	public
	 * @return	string  Returns the javascript script files registered with the form including any the jquery.validate plugin if javascript_validate is set to TRUE
	 */	
	function js_output()
	{
		$output = '';
		// include js files
		if ($this->is_javascript_submit() OR $this->is_javascript_submit())
		{
			$output .= "\n".js('jquery.validate.min', FORMS_FOLDER);
			$output .= "\n".js('additional-methods.min', FORMS_FOLDER);
			$output .= "\n".js('forms', FORMS_FOLDER);
			$config_js = $this->fuel->forms->config('js');
		}
		if (!empty($config_js))
		{
			$output .= "\n".js($config_js);
		}
		if ($this->has_js())
		{
			$output .= "\n".js($this->js);
		}
		return $output;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the rendered form fields as variables which can be consumbed by views/blocks and including an array of rendered field output, labels, and the form object itself.
	 * 
	 <code>
	$form = $this->fuel->forms->create('myform', array('fields' => array('name' => array('required' => TRUE), 'email' => array('required' => TRUE))));
	echo $form->rendered_vars();
	 </code>
	 *
	 * @access	public
	 * @return	array  Returns an array of variables that can be used in views/block files
	 */	
	function rendered_vars($form_fields)
	{
		$rendered_fields = array();
		$vars = array();
		
		foreach($form_fields as $key => $form_field)
		{
			$rendered_fields[$key]['field'] = $this->CI->form_builder->create_field($form_field);
			$rendered_fields[$key]['label'] = $this->CI->form_builder->create_label($form_field);
			$rendered_fields[$key]['key'] = $key;
			$vars[$key.'_field'] = $rendered_fields[$key]['field'];
			$vars[$key.'_label'] = $rendered_fields[$key]['label'];
		}
		$vars['fields'] = $rendered_fields;
		$vars['form'] = $this;
		return $vars;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of form fields that can be used with the Form_builder class
	 * 
	 <code>
	$form = $this->fuel->forms->create('myform', array('fields' => array('name' => array('required' => TRUE), 'email' => array('required' => TRUE))));
	foreach($form->form_fields() as $name => $field)
	{
		echo $this->form_builder->create_field($field);
	}
	 </code>
	 *
	 * @access	public
	 * @return	array  Returns an array of form fields
	 */	
	function form_fields()
	{
		// setup fields for the form
		$form_fields = array();

		$is_block_view = $this->is_block_view();
		foreach($this->fields as $f)
		{
			$form_fields[$f->name] = $f->render($is_block_view);
		}

		// antispam
		$antispam_params = $this->get_antispam_params();
		if (!empty($antispam_params['method']))
		{
			$form_fields['__antispam__'] = array('type' => 'antispam', 'display_label' => FALSE);
			$form_fields['__antispam__'] = array_merge($form_fields['__antispam__'], $antispam_params);
		}
		$form_fields['return_url'] = array('type' => 'hidden', 'value' => $this->get_return_url());

		return $form_fields;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns custom field information to be used with rendering the form via Form_builder.
	 * 
	 * @access	protected
	 * @return	array
	 */	
	protected function get_custom_fields()
	{
		include(FORMS_PATH.'config/custom_fields.php');
		$custom_fields = $this->fuel->forms->config('custom_fields');
		$custom_fields = array_merge($fields, $custom_fields);
		return $custom_fields;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns information about the anti SPAM method to use for the form
	 * 
	 * @access	protected
	 * @return	array 
	 */	
	protected function get_antispam_params()
	{
		if (is_json_str($this->anti_spam_method))
		{
			return json_decode($this->anti_spam_method, TRUE);
		}
		if (is_string($this->anti_spam_method))
		{
			return array('method' => $this->anti_spam_method);
		}
		return $this->anti_spam_method;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns an array of posted form variables that can be used for capturing in the database or sent via email
	 * 
	 * @access	protected
	 * @return	array 
	 */	
	protected function clean_posted($posted = array())
	{
		if (empty($posted)) 
		{
			$posted = $this->CI->input->post();
		}
		$return = array();
		if (!empty($posted))
		{
			$fields = $this->fields;

			foreach($posted as $key => $val)
			{
				if (!preg_match('#^_.+#', $val) AND isset($fields[$key]) AND $fields[$key]->type != 'hidden')
				{
					$return[$key] = $val;
				}
			}	
		}
		return $return;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Runs validation found within the forms configuration if a form is created within the $config['forms']['forms']
	 * 
	 * @access	protected
	 * @return	object 	Returns the validator object 
	 */	
	protected function run_other_validation()
	{
		// grab any validation that may be set in the config file
		$config = $this->fuel->forms->config();
		$validation = (isset($config['forms'][$this->slug]['validation'])) ? $config['forms'][$this->slug]['validation'] : array();
		if (!empty($validation))
		{
			$this->add_validation($validation);
		}

		if (!empty($this->validation))
		{
			$this->add_validation($this->validation);	
		}
	}
	
	// --------------------------------------------------------------------

	/**
	 * Returns the validator object used for validating the front end
	 *
	 * @access	protected
	 * @return	object
	 */	
	protected function &get_validator()
	{
		return $this->CI->validator;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns TRUE/FALSE based on if the entries should be saved or not.
	 *
	 * @access	protected
	 * @return	boolean
	 */	
	protected function get_save_entries()
	{
		return is_true_val($this->save_entries);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns TRUE/FALSE based on if javascript submission should be used.
	 *
	 * @access	protected
	 * @return	boolean
	 */	
	protected function get_javascript_submit()
	{
		return is_true_val($this->javascript_submit);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns TRUE/FALSE based on if the javascript validation should be saved or not.
	 *
	 * @access	protected
	 * @return	boolean
	 */	
	protected function get_javascript_validate()
	{
		return is_true_val($this->javascript_validate);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the form "action" URL. If no value is specified it will be "forms/{slug}"
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_form_action()
	{
		if (empty($this->form_action))
		{
			// if ($this->fuel->forms->config('javascript_submit'))
			// {
				return site_url('forms/'.$this->slug);
			//}
			//return site_url(uri_path());	
		}
		return site_url($this->form_action);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the module name to be used if a block is being used to render the form.
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_block_view_module()
	{
		if (empty($this->block_view_module))
		{
			return 'application';
		}
		return $this->block_view_module;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the the email's from value and will pull from the form's config file if no value is set
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_email_from()
	{
		if (empty($this->email_from))
		{
			return $this->fuel->forms->config('email_from');
		}
		return $this->email_from;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the the email's subject line and will pull from the form's config file if no value is set
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_email_subject()
	{
		if (empty($this->email_subject))
		{
			return $this->fuel->forms->config('email_subject');
		}
		return $this->email_subject;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the the email's subject line and will pull from the form's config file if no value is set
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_email_message()
	{
		$output = '';

		$fields = $this->fields;
		$posted = $this->clean_posted();
		$posted['URL'] = site_url(uri_string());

		if (!empty($posted))
		{
			if (!empty($this->email_message))
			{
				// used to escape the placeholder issues with for example the "name" property
				$msg = str_replace(array('{{', '}}'), array('{', '}'), $this->email_message);
				$output = parse_template_syntax($msg, $posted, TRUE);
			}
			else
			{
				foreach($posted as $key => $val)
				{
					$output .= humanize($key).": $val\n";
				}
			}
		}
		return lang('forms_email_message', $this->name, $output);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Gets the return URL to be used after the submission of the form
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_return_url()
	{
		if (empty($this->return_url))
		{
			return site_url(uri_string());
		}
		return $this->return_url;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the submit button text
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_submit_button_text()
	{
		if (empty($this->submit_button_text))
		{
			return lang('forms_submit_button_default');
		}
		return $this->submit_button_text;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the reset button text
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_reset_button_text()
	{
		return $this->reset_button_text;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the text to be display after submisssion
	 *
	 * @access	protected
	 * @return	string
	 */	
	protected function get_after_submit_text()
	{
		if (empty($this->after_submit_text))
		{
			return lang('forms_after_submit');
		}
		return $this->after_submit_text;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Magic method for capturing method calls on the record object that don't exist. Allows for "get_{field}" to map to just "{field}" as well as "is_{field}"" and "has_{field}"
	 *
	 * @access	public
	 * @param	object	method name
	 * @param	array	arguments
	 * @return	array
	 */	
	public function __call($method, $args)
	{
		if (preg_match( "/^set_(.*)/", $method, $found))
		{
			if (property_exists($this, $found[1]))
			{
				$method = $this->$found[1];
				$this->$method = $args[0];
				return TRUE;
			}
		}
		else if (preg_match("/^get_(.*)/", $method, $found))
		{
			if (property_exists($this, $found[1]))
			{
				$method = $this->$found[1];
				return $this->$method;
			}
		}
		elseif (preg_match("/^is_(.*)/", $method, $found))
		{
			if (property_exists($this, $found[1]))
			{
				if (!empty($found[1]))
				{
					return is_true_val($this->$found[1]);
				}
			}
		}
		else if (preg_match("/^has_(.*)/", $method, $found))
		{
			if (property_exists($this, $found[1]))
			{
				return !empty($this->$found[1]);
			}
		}
		return FALSE;
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Magic method to set first property, method, then field values
	 *
	 * @access	public
	 * @param	string	field name
	 * @param	mixed	
	 * @return	void
	 */	
	public function __set($var, $val)
	{
		if (method_exists($this, 'set_'.$var))
		{
			$set_method = "set_".$var;
			$this->$set_method($val);
		}
		else if (property_exists($this, $var))
		{
			$this->$var = $val;
		}
		else
		{
			throw new Exception('property '.$var.' does not exist.');
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Magic method to return first property, method, then field values 
	 *
	 * @access	public
	 * @param	string	field name
	 * @return	mixed
	 */	
	public function __get($var)
	{
		$output = NULL;

		// first class property has precedence
		if (method_exists($this, "get_".$var))
		{
			$get_method = "get_".$var;
			$output = $this->$get_method();
		}
		else if (property_exists($this, $var))
		{
			$output = $this->$var;
		}
		
		return $output;
	}
}

// ------------------------------------------------------------------------

/**
 * Fuel Form field object 
 *
 */

//  ------------------------------------------------------------------------
class Form_field extends Fuel_base_library {

	protected $params = array('type' => 'text');

	/**
	 * Constructor - Sets parameters
	 */
	function __construct($params = array())
	{
		parent::__construct();
		$this->initialize($params);
	}
	
	// --------------------------------------------------------------------
	
	/**
	 * Set object parameters
	 *
	 * @access	public
	 * @param	array	Config preferences
	 * @return	void
	 */
	public function set_params($params)
	{

		if (!is_array($params) OR empty($params)) return;

		// set invalid base properties that can be set
		$invalid_props = array('CI', 'fuel');
		foreach ($params as $key => $val)
		{
			if (!in_array($key, $invalid_props) AND substr($key, 0, 1) != '_')
			{
				$this->$key = $val;
			}
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the values of the form fields in an array
	 *
	 * @access	protected
	 * @return	array
	 */
	public function values()
	{
		return $this->params;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns TRUE/FALSE depending on if the field is considered required
	 *
	 * @access	protected
	 * @return	boolean
	 */
	public function is_required()
	{
		return !empty($this->required);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Renders the form field
	 *
	 * @access	protected
	 * @return	string
	 */
	public function render($string = TRUE)
	{
		$field = '';
		$layout = $this->fuel->forms->field_layout($this->type);

		if (!empty($layout))
		{
			$field = $layout->frontend_render($this);
			if ($string)
			{
				return $this->CI->form_builder->create_field($field);
			}
		}
		return $field;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Returns the label value
	 *
	 * @access	protected
	 * @return	string
	 */
	protected function get_label()
	{
		if (empty($this->params['label']))
		{
			return ucfirst(str_replace('_', ' ', $this->params['name']));
		}
		return $this->params['label'];
	}

	// --------------------------------------------------------------------
	
	/**
	 * Magic method to set first property, method, then field values
	 *
	 * @access	public
	 * @param	string	field name
	 * @param	mixed	
	 * @return	void
	 */	
	public function __set($var, $val)
	{
		if (method_exists($this, 'set_'.$var))
		{
			$set_method = "set_".$var;
			$this->$set_method($val);
		}
		else
		{
			$this->params[$var] = $val;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Magic method to return first property, method, then field values 
	 *
	 * @access	public
	 * @param	string	field name
	 * @return	mixed
	 */	
	public function __get($var)
	{
		$output = NULL;

		// first class property has precedence
		if (method_exists($this, "get_".$var))
		{
			$get_method = "get_".$var;
			$output = $this->$get_method();
		}
		else if (array_key_exists($var, $this->params))
		{
			$output = $this->params[$var];
		}
		
		return $output;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Magic method that returns TRUE/FALSE depending if the parameter is set
	 *
	 * @access	public
	 * @param	string	field name
	 * @return	boolean
	 */	
	public function __isset($var)
	{
		return isset($this->params[$var]);
	}
}