<?php
class Forms extends CI_Controller {
	
	public function __construct()
	{
		parent::__construct();
		$this->load->helper('ajax');
		$this->load->library('session');
	}

	public function _remap($slug)
	{
		$form = $this->fuel->forms->get($slug);

		$return_url = ($this->input->get_post('return_url')) ? $this->input->get_post('return_url') : $form->get_return_url();

		if (!$form->process())
		{
			$this->session->set_flashdata('posted', $this->input->post());

			if (is_ajax())
			{
				// Set a 200 (okay) response code.
				set_status_header('500');
				echo display_errors(NULL, '');
				exit();
			}
			else
			{
				$this->session->set_flashdata('error', $form->errors());
				redirect($return_url);
			}
		}
		else
		{
			if (is_ajax())
			{
				// Set a 200 (okay) response code.
				set_status_header('200');
				echo $form->after_submit_text;
				exit();
			}
			else
			{
				$this->session->set_flashdata('success', TRUE);
				redirect($return_url);
			}
		}

	}
}