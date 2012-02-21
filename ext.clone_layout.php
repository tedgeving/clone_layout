<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * clone_layout Extension
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Extension
 * @author		Ted Geving
 * @link		http://tedgeving.com/ee
 */

class Clone_layout_ext {
		
	
	public $settings 		= array();
	public $description		= 'Clone/Copy a Publish Page Layout from one channel for a different group and/or channel';
	public $docs_url		= 'http://tedgeving.com/ee';
	public $name			= 'Publish Layout';
	public $settings_exist	= 'y';
	public $version			= '1.0';
	private $EE;
	
	/**
	 * Constructor
	 *
	 * @param mixed Settings array or empty string if none exist.
	 */
	public function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->settings = $settings;
		$this->EE->lang->loadfile('clone_layout'); // need to load lang file or no bueno.
	}
	
	// ----------------------------------------------------------------------
	
	/**
	 * Activate Extension
	 *
	 * This function enters the extension into the exp_extensions table
	 *
	 * @see http://codeigniter.com/user_guide/database/index.html for
	 * more information on the db class.
	 *
	 * @return void
	 */
	public function activate_extension()
	{
		// Setup custom settings in this array.
		$this->settings = array();
		
		$data = array(
			'class'		=> __CLASS__,
			'settings'	=> serialize($this->settings),
			'version'	=> $this->version,
			'enabled'	=> 'y'
		);
		
		$this->EE->db->insert('extensions', $data);
		
	}	

	// ----------------------------------------------------------------------

	/**
	 * Disable Extension
	 *
	 * This method removes information from the exp_extensions table
	 *
	 * @return void
	 */
	function disable_extension()
	{
		$this->EE->db->where('class', __CLASS__);
		$this->EE->db->delete('extensions');
	}
	
	// ----------------------------------------------------------------------
	
	/**
	 * Update Extension
	 *
	 * This function performs any necessary db updates when the extension
	 * page is visited
	 *
	 * @return 	mixed	void on update / false if none
	 */
	function update_extension($current = '')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
	}
	
	// ----------------------------------------------------------------------
	
	/**
	 * Settings Form
	 * @param   Array   Settings
	 * @return  Mixed	
	 */
	function settings_form($current)
	{
		$this->EE->load->library('javascript');
		$this->EE->load->library('table');
		$this->EE->load->helper('form');
		$this->EE->cp->set_variable('cp_page_title', $this->EE->lang->line('clone_layout_module_name'));
		
		// Get data to build Create New Layout form.
		$channel_name = $this->fetch_channels();
		$channel_layout_view = $this->fetch_layouts();
		$groups = $this->fetch_groups();
	    
		// Get data to build Applied Layout Views form.
		$existing_layouts = $this->fetch_page_layouts();
		
		$vars = array('channel_name'=>$channel_name, 'channel_layout_view'=>$channel_layout_view, 'groups'=> $groups, 'existing_layouts'=>$existing_layouts);
		return  $this->EE->load->view('index.php', $vars, TRUE);
	}

	// ----------------------------------------------------------------------
	
	/**
	* Save Settings
	*
	* This function has 2 actions (NOTE, EE 2.0  Extension have only one save method 
	* availble, as a result this fucntion is a bit KLUDGY)
	*
	* @return void 
	**/
	function save_settings()
	{
		$status = NULL;	
	  
		if($this->EE->input->post('method') == 'new_layout')
		{
			// ACTION 1.
			// Save a new Publish Page Layout only when one does not exist for the specified parameters.	
		    $member_group = $this->EE->input->post('member_group');
			$channel_id = $this->EE->input->post('channel_id');
			$layout_id = $this->EE->input->post('layout_id');
			
			if($this->check_layouts($member_group, $channel_id) == FALSE)
			{	
				$status = $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('existing_layout'));
			}
			else
			{
				
				$field_group = $this->check_field_group( $channel_id, $layout_id);
				
				// Check field group assignment, should be the same for the channel we are 
				// cloning the layout to.
				
				if($field_group == TRUE)
				{			
					$this->save_new_layout($member_group, $channel_id, $layout_id);
					$status = $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('layout_saved'));
				}
				else
				{
					$status = $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('field_group_error'));
				}
			}
		
		}
		
		if ($this->EE->input->post('method') == 'delete_layout') 
		{
			// ACTION 2.
			// Delete one or more existing Publish Page Layouts
			$layout_ids =$this->EE->input->post('layout_ids');
			$this->delete_layout($layout_ids);
		
		}
		
		return $status;
	}
	
	// ----------------------------------------------------------------------
	
	/**
	* This functions saves a new Publish Page Layout, only called if there is not an existing entry
	* with the same $member_group and channel_id
	*
	* @param	string	$member_group
	* @param	string	$channel_id
	* @param	string	$layout_id
	* @return	mixed
	* @todo
	* - use active record to build the query as there might be a problem with hard coding the DB prefix
	**/
	private function save_new_layout($member_group=NULL, $channel_id=NULL, $layout_id=NULL)
	{
		// Basic pattern for duplicating Publish Page Layout based on existing channel
		// Will cause php errors in the control pannel if the same member group has more than 1 layout for that channel 
		//
		// INSERT INTO exp_layout_publish (site_id, member_group, channel_id, field_layout) 
		// SELECT `site_id`, `member_group`, `channel_id`, `field_layout`  
		// FROM exp_layout_publish WHERE layout_id=x
		
		$query = $this->EE->db->query('INSERT INTO exp_layout_publish (site_id, member_group, channel_id, field_layout) 
		                      SELECT site_id,'.$member_group .','.$channel_id.', field_layout  
							  FROM exp_layout_publish WHERE layout_id='.$layout_id);
	
		return $query;
	}
	
	// ----------------------------------------------------------------------
	
	/*
	* Check Field Group Assignment 
	*
	* This function checks the value of the channel_field group.  The channel that 
	* the layout is being cloned to needs to have the save field_group otherwise the
	* control pannel will throw errors.
	*
	* @param	string	$channel_id
	* @param	string	$layout_id
	*
	* @return	bool
	*/
	
	
	private function check_field_group($channel_id =NULL, $layout_id = NULL)
	{
		$flag = FALSE;
		
		/// 1. Get the field group assignment for the channel we are cloning to
		$this->EE->db->select('channel_id, field_group');
		$this->EE->db->where('channel_id', $channel_id);
		$this->EE->db->from('channels');
		$channel = $this->EE->db->get();
		
		$field_group = $channel->row()->field_group;
		
		
		/// 2. Get the field group assingment for the channel we are cloning from
		$this->EE->db->select();
		$this->EE->db->where('layout_id',  $layout_id);
		$this->EE->db->from('layout_publish');
		$channel_id =  $this->EE->db->get();
		
		$cloned_channel_id = $channel->row()->channel_id;
		
		
		$this->EE->db->select('channel_id, field_group');
		$this->EE->db->where('channel_id', $cloned_channel_id);
		$this->EE->db->from('channels');
		$cloned_channel = $this->EE->db->get();
		
		$cloned_field_group = $channel->row()->field_group;
		
		
		// Checck for NULL, does not make sense to clone a channel w/o
		// an assigned field group
				
		if(($cloned_field_group != NULL) OR ($field_group != NULL))
		{
			if($cloned_field_group == $field_group)
			{
				$flag = TRUE;
			}
		}
		return $flag;
	}
	
	// ----------------------------------------------------------------------	
	
	
	/**
	* This function deletes a Publish Page Layout or multiple layouts
	*	
	* @param array $layout
	* @todo
	* - added confirm delete dialog, need to cross browser test the $.submit() function.
	*/
	private function delete_layout($layout)
	{
		$status = NULL;
		
		if($layout != FALSE)
		{
			foreach ($layout as $id)
			{
				$this->EE->db->where('layout_id', $id);
				$this->EE->db->delete('layout_publish');
				$status = $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('layouts_deleted'));
			}
		}
		else
		{	
			$layouts_not_deleted =$this->EE->lang->line('test');
			$status = $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('layouts_not_deleted'));
		}
		
		return $status;
	}

	// ----------------------------------------------------------------------	
	
	/**
	 * This function checks the DB for a an existing Publish Page Layout.
	 * Returns an error if there is already a layout for the same channel and same group. 
	 * 
	 * @return Bool.
	*/
	private function check_layouts($member_group = NULL, $channel_id= NULL)
	{
		$flag = TRUE;
		 
		$this->EE->db->select('member_group, channel_id');
		$this->EE->db->where('member_group', $member_group);
		$this->EE->db->where('channel_id', $channel_id);
		$this->EE->db->from('layout_publish');
		$query = $this->EE->db->get();
		
		if(count($query->result()) > 0)
		{
			// We already have a layout for the memebr_group and channel_id, trigger error and exit.			
			$flag = FALSE;  
		}
		
		return $flag;	
	}
	
	// ----------------------------------------------------------------------
	
	/**
	 * Fetch Channels
	 *
	 * This function gets a list of channels for a form widget.
	 *
	 * @return array
	 */
	private function fetch_channels()
	{
		
		$query = $this->EE->channel_model->get_channels();
		
		if ($query->num_rows() > 0)
		{
			foreach ($query->result() as $row)
			{
				$channel_name[$row->channel_id] = $row->channel_title;
			}
		}
		else
		{
		    $channel_name = NULL;
		}
		
		return $channel_name;
	}
	
	
	// ----------------------------------------------------------------------

	/**
	 * Fetch Layouts 
	 *
	 * This function gets a list of Publish Page Layouts for a form widget
	 *
	 * @return mxed
	 */
	private function fetch_layouts()
	{
		 $this->EE->db->select('*');  //TODO reduce query
		 $this->EE->db->from('layout_publish');
		 $this->EE->db->join('channels', 'channels.channel_id = layout_publish.channel_id');
		 $this->EE->db->join('member_groups', 'layout_publish.member_group = member_groups.group_id');
		 $this->EE->db->order_by('channels.channel_name', 'ASC');
		 
		 $query = $this->EE->db->get(); 
		 
		if ($query->num_rows() > 0)
		{
			foreach ($query->result() as $row)
			{
				$layout[$row->layout_id] = $row->channel_title.' <br/> (used by group) <br/> '.$row->group_title;
			}
		
		}
		else
		{
		    $layout = NULL;
		}

		return $layout;
	}	
	
	// ----------------------------------------------------------------------
	
	/**
	* Fetch Groups
	*
	* This function gets a list of member_groups for a form widget
	*
	* @return Mixed
	* @todo
	* - remove groups, banned, guests, pending and members from the list?
	* - set order of groups function?
	*/
	private function fetch_groups()
	{
		$this->EE->db->select("group_id, group_title");
		$this->EE->db->from("member_groups");
		$this->EE->db->where("site_id", $this->EE->config->item('site_id'));
		$this->EE->db->order_by('group_title');

		$query = $this->EE->db->get();
		
		if ($query->num_rows() > 0)
		{
			foreach ($query->result() as $row)
			{
				$groups[$row->group_id] = $row->group_title;
			}
		
		}
		else
		{
		    $groups = NULL;
		}

		return $groups;
	}	
	
	// ----------------------------------------------------------------------
	
	/**
	* Fetch Page Layouts
	*
	* @return array
	*/
	private function fetch_page_layouts()
	{
		$layouts = array();
		$this->EE->db->select('*');
		$this->EE->db->from('layout_publish');
		$this->EE->db->join('channels', 'channels.channel_id = layout_publish.channel_id');
		$this->EE->db->join('member_groups', 'layout_publish.member_group = member_groups.group_id');
		$this->EE->db->order_by("channels.channel_name", "ASC"); 
		$query = $this->EE->db->get();
		 
		if ($query->num_rows() > 0)
		{
			
			$i = 0;
			foreach($query->result() as $row)
        	{
                $layouts[$i] =  $row;
           
            $i++;
        	}
		
		}
		else
		{
			$layouts = NULL;
		}

		return $layouts;
	}	
	
	// ----------------------------------------------------------------------
	
}

/* End of file ext.clone_layout.php */
/* Location: /system/expressionengine/third_party/clone_layout/ext.clone_layout.php */