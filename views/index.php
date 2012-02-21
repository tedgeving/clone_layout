

	
<h3>Create New Layout View</h3>
<?php 
	// Check to make sure there is at least one channel and one publish page layout 
	if($channel_layout_view != NULL && $channel_name != NULL ){
?>
	
	
	<?=form_open('C=addons_extensions'.AMP.'M=save_extension_settings'.AMP.'file=clone_layout');?>
	<?=form_hidden('method', 'new_layout');?>
	<?php
	
	$this->table->set_template($cp_pad_table_template);
	$this->table->set_heading(
		array('data' => lang('preference'), 'style' => 'width:50%;'),
		lang('setting')
	);
	
	$this->table->set_heading('Channel Name', 'Publish Layout View', 'Group');
	$this->table->add_row(form_dropdown('channel_id', $channel_name), form_dropdown('layout_id', $channel_layout_view), form_dropdown('member_group', $groups));
	
	echo $this->table->generate(); 
	?>
	
	<p><?=form_submit('submit', lang('save_new_layout'), 'class="submit"')?></p>
	<?php $this->table->clear()?>
	<?=form_close()?>
<?php
	}
	else
	{
	 echo "<p>At least one Channel and/or one Layout is needed to clone a publish page layout</p>";
	}
?> 
<br/>
<h3>Applied Layout Views</h3>
<div id="apv">

<?php
// Check to make sure there is at least one channel and one publish page layout 
if($existing_layouts != NULL)
{ 
?>
	
	<?=form_open('C=addons_extensions'.AMP.'M=save_extension_settings'.AMP.'file=clone_layout');?>
    <?=form_hidden('method', 'delete_layout');?>
    <?php
    $this->table->set_template($cp_pad_table_template);
    $this->table->set_heading(
        array('data' => lang('preference'), 'style' => 'width:50%;'),
        lang('setting'));
        $this->table->set_heading('Channel Name', 'Publish Layout View', 'Preview Layout', '<input  type="checkbox" id="all"');
        
        
        
        foreach($existing_layouts as $row)
        {
            
            $this->table->add_row(	$row->channel_title, 
                                    $row->group_title, 
                                    '<a href="'.BASE.AMP.'&D=cp&C=content_publish&M=entry_form&channel_id='.$row->channel_id.AMP.'layout_preview='.$row->member_group.'" target="_blank">
                                    '.lang('Preview Layout').'</a>', 
                                    form_checkbox( array('name'=>'layout_ids[]','value'=>$row->layout_id, 'class'=>'checkbox')));
        
        }
        echo $this->table->generate();       
    
    ?>
    <p><?=form_submit('s', lang('delete'), 'class="submit" id="opener"')?></p>
    <?php $this->table->clear()?>
    <?=form_close()?>
    
  

<?
}
else
{
echo "<p>At least one Channel and/or one Layout is needed to delete/view a publish page layout</p>";
}
 ?>
 </div>
 <?php
	// JS 
	// Toggle check boxes
	// Confirm delete dialog.
	// Pretty sure this code will only work in modern browsers, sorry IE6.
    
	$this->javascript->output('
	
	$(document).ready(function() {
	 $("#all").toggle(
		 function() {
		 $("td").find("input[type=checkbox]").attr("checked", true);
		 },
		 function() {
		 $("td").find("input[type=checkbox]").attr("checked", false);
	 });
	
	var $dialog = $("<div></div>")
				.html("<p><strong>Are you sure you want to permanently delete the selected layouts?</strong></p><br/><p class=\"notice\">THIS ACTION CANNOT BE UNDONE.</p>")
				.dialog({
					autoOpen: false,
					title: "Delete Publish Layouts",
					modal: true,
					buttons: { "Delete Publish Layouts": function() {$("#apv form").submit();  $(this).dialog("close");},
                 	"Cancel": function() { $(this).dialog("close");
 					}}
		});

		$("#opener").click(function() {
			$dialog.dialog("open");
	
			return false;
		});
		
	});
	
					
	');
?>
<?php
/* End of file index.php */
/* Location: ./system/expressionengine/third_party/link_truncator/views/index.php */