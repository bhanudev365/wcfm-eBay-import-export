	<?php global $WCFM, $wp_query; ?>
	<?php
	$vendor_id = get_current_user_id(); 
	$vendor_product_request = getCurrentVendorRequest($vendor_id,'products');
	$in_queued = get_user_meta($vendor_id,'ebay_in_queued',true);
	$proccessing_bar = get_user_meta($vendor_id,'ebay_proccessing_bar',true);
	$functions = new wcfmEbayFunctions;
	// echo '<pre>';
	// print_r(getCurrentVisitorCountry());
	//echo get_option('timezone_string');
	?>
	<div class="collapse wcfm-collapse" id="wcfm_eBay_vendor_form_listing">
		<div class="wcfm-page-headig">
			<span class="wcfmfa fa fa-cubes"></span>
			<span class="wcfm-page-heading-text"><?php _e( 'eBay Import Export Settings', 'wcfm-custom-menus' ); ?></span>
		</div>
		<div class="wcfm-collapse-content">
			<div id="wcfm_page_load"></div>
			<div class="wcfm-container wcfm-top-element-container">
				<h2><span class="fa-sync"></span>&nbsp;&nbsp;<?php _e('eBay Store Settings', 'wcfm-custom-menus' ); ?></h2>
				<div class="wcfm-clearfix"></div>
			</div>
			<div class="wcfm-clearfix"></div><br />
			<div class="wcfm-container">
				<div id="wcfm_eBay_vendor_form_expander" class="wcfm-content">
					<?php
					$wcfm_ie_options = array(); 
					$wcfm_ie_options = get_option('wcfm_ie_form_setting');
					$vendor_id = get_current_user_id();

					$form_setting_sold_items = 'ignore';
					$form_setting_revise = 'true';
					$form_setting_discount_price  = false;
					$form_setting_all_items = '';
					$form_setting_without_bin = false;
					$form_setting_unsold_items = false;
					$form_setting_import_feedback = false;
					$quantity_update_on_ebay = false;

					if($wcfm_ie_options[$vendor_id] && sizeof($wcfm_ie_options[$vendor_id])>0){
						if(!isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_price'])){
							$form_setting_discount_price  = true;
						}
						if(!isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_without_bin'])){
							$form_setting_without_bin  = true;
						}
						if(!isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_unsold_items'])){
							$form_setting_unsold_items  = true;
						}
						if(!isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_amount'])){
							$form_setting_discount_amount  = 0;
						}
						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_sold_items']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_sold_items'])){
							$form_setting_sold_items = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_sold_items'];
						}
						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_revise']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_revise'])){
							$form_setting_revise = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_revise'];
						}

						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_price']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_price'])){
							$form_setting_discount_price = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_price'];
						}
						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_amount']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_amount'])){
							$form_setting_discount_amount = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_discount_amount'];
						}
						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_without_bin']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_without_bin'])){
							$form_setting_without_bin = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_without_bin'];
						}
						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_unsold_items']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_unsold_items'])){
							$form_setting_unsold_items = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_unsold_items'];
						}
						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_import_feedback']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_import_feedback'])){
							$form_setting_import_feedback = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_import_feedback'];
						}
						if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_quantity_update_on_ebay']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_quantity_update_on_ebay'])){
							$quantity_update_on_ebay = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_quantity_update_on_ebay'];
						}
						// if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_days_synchronize']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_days_synchronize']) && $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_days_synchronize']){
						// 	if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_days_to_activate']) && !empty($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_days_to_activate'])){
						// 		$days_to_activate = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_days_to_activate'];
						// 	}else{
						// 		$days_to_activate = 1;
						// 	}
						// }else{
						// 	$days_to_activate = 1;
						// }
					}
					?>
					<?php 
					$schedule_queued = $functions->getSellerSingleCronScheduleEvent(get_current_user_id(),'products','wcfm_schedule_import_products',true);
					if($schedule_queued){ ?>
						<div class="alert alert-info alert-dismissible fade show" role="alert" style="display: inline-block !important;">
							<span>
								Products will be import after <b><?php echo human_time_diff(time(),strtotime($schedule_queued['expire'])); ?></b>
							</span>
							<div style="float: right;">
								<form action="" method="post">
									<input type="hidden" name="action" value="remove_schedule">
									<input type="hidden" name="type" value="products">
									<button class="button_large submit_import_job" type="submit">Remove Next Schedule</button>
								</form>
							</div>
						</div>
					<?php }  ?>
					<div class="alert alert-info alert-dismissible fade show" role="alert">
						<b>Are your listings from a US site?</b> At this time, Fizno can only import items that are hosted on the US version of eBay.
					</div>
					<?php echo displayAlert(); ?>
					<?php
					$wcfm_ie_alert = array();
					$wcfm_ie_alert = get_user_meta($vendor_id,'wcfm_ie_alert',true);

					if(isset($wcfm_ie_alert[$vendor_id]['alert']) && !empty($wcfm_ie_alert[$vendor_id]['alert'])){ 
						$notify = '<div class="alert alert-'.$wcfm_ie_alert[$vendor_id]['alert']['type'].' alert-dismissible fade show notify" role="alert">
						'.$wcfm_ie_alert[$vendor_id]['alert']['msg'].'
						</div>';
						unset($wcfm_ie_alert[$vendor_id]);
						update_user_meta($vendor_id,'wcfm_ie_alert',$wcfm_ie_alert);
						echo $notify;
					}
					$multi_alerts= array();
					$multi_alerts = get_option('wcfm_ie_multi_alert');
					if(isset($multi_alerts[$vendor_id]) && !empty($multi_alerts[$vendor_id])){
						foreach ($multi_alerts[$vendor_id] as $timestamp => $alert_setting) {
							$notify = '<div class="alert alert-'.$alert_setting['alert']['type'].' alert-dismissible fade show notify" role="alert">
							'.$alert_setting['alert']['msg'].'
							</div>';
							unset($multi_alerts[$vendor_id][$timestamp]);
							update_option('wcfm_ie_multi_alert',$multi_alerts);
							echo $notify;
						}
					}
					?>
					<?php if(sizeof($vendor_product_request)>0){ ?>
						<?php if(isset($in_queued) && $in_queued == true){ ?>
							<div class="alert alert-info alert-dismissible fade show queued" role="alert">
								<b>Your import is queued.</b> We'll begin processing it soon.
								<span style="float: right;">Created <?php echo date('d, F Y h:i a',strtotime($vendor_product_request['date_added']));  ?></span>
							</div>
						<?php } ?>
						<?php if(isset($proccessing_bar) && $proccessing_bar == true){ ?>
							<?php
							$total = '0 / '.$vendor_product_request['total_products'];
							$percentage = 0;
							if($vendor_product_request['total_products'] && $vendor_product_request['done']){
								$percentage = $vendor_product_request['done']/$vendor_product_request['total_products']*100;
								$total = '<b><span id="done">'.$vendor_product_request['done'] .'</span> of <span id="total_products">'.$vendor_product_request['total_products'] .'</span> unique listings</b>';
							}
							?>
							<div class="progress">
								<div class="progress-bar progress-bar-striped progress-bar-animated" id="progress_bar" style="width:<?php echo $percentage; ?>%"><div class="progress_text"><?php echo ceil($percentage); ?>%</div></div>
							</div>
							<div>
								<span><?php echo $total; ?></span>
							</div>
						<?php } ?>
					<?php } ?>
					<?php if(sizeof($vendor_product_request)>0){ ?>
						<div id="ebay_import_form" class="item_import_form">
							<div class="import_sync_info">
								<div class="import_sync_info_panel">
									<div class="import_sync_info_panel_contents">
										<h3 class="section_title">Current Import Settings</h3>
										<ul class="sync_details">
											<h3> Remove item </h3>
											<?php if($form_setting_sold_items=='remove'){ ?>
												<li>Remove all items that were removed, expired, or ended for any reason on eBay</li>
											<?php } ?>
											<?php if($form_setting_sold_items=='remove_sold'){ ?>
												<li>Only remove items that were sold or ended on eBay (do not remove when expired)</li>
											<?php } ?>
											<?php if($form_setting_sold_items=='ignore'){ ?>
												<li>Never remove items</li>
											<?php } ?>
											<h3> All, New, Used and revised items </h3>
											<?php if($form_setting_revise=='false'){ ?>
												<li>Import new items</li>
											<?php } ?>
											<?php if($form_setting_revise=='true'){ ?>
												<li>Import new and revised items (overwrite all existing items)</li>
											<?php } ?>
											<?php if($form_setting_discount_price || $form_setting_without_bin || $form_setting_unsold_items){ ?>
												<h3> Item price and item details </h3>
											<?php } ?>
											<?php if($form_setting_discount_price){
												$discount_amount_text = '0%';
												if($form_setting_discount_amount){
													$discount_amount_text = sprintf('%s%s',$form_setting_discount_amount,'%');
												}
												?>
												<li>Discount prices by <?php echo $discount_amount_text; ?> saved on Fizno.</li>
											<?php } ?>
											<?php if($form_setting_without_bin){ ?>
												<li>Import auctions without a "Buy it Now" price</li>
											<?php } ?>
											<?php if($form_setting_unsold_items){ ?>
												<li>Import expired items from your "Unsold" folder</li>
											<?php } ?>
											<?php if($form_setting_import_feedback){ ?>
												<h3> Advanced Options</h3>
											<?php } ?>
											<?php if($form_setting_import_feedback){ ?>
												<li>Include eBay item feedback</li>
											<?php } ?>
											<?php if($quantity_update_on_ebay){ ?>
												<li>Update the item quantity on eBay after a Fizno sale</li>
											<?php } ?>
											<?php /* if(isset($days_to_activate) && !empty($days_to_activate)){ ?>
												<li>Sync your booth with eBay and wait <?php echo $days_to_activate; ?> days to post any new items</li>
											<?php }else{ ?>
												<li>Sync your booth with eBay and don't wait to post any new items</li>
											<?php } */ ?>
										</ul>
										<p>
											<form action="" id="cancel_importing_form" method="post">
												<input type="hidden" name="type" value="products">
												<input type="hidden" name="action" value="wcfm_ie_cancel_request">
												<div>
													<div>
														<a class="cancel_sync_button" data-trigger="cancel_importing" href="javascript:void(0)">Cancel import</a>
													</div>
												</div>
											</form>
										</p>
									</div>
								</div>
								<?php  ?>
								<?php  $vendor_schedule = $functions->getSellerSingleCronScheduleEvent(get_current_user_id(),'products','wcfm_schedule_import_products'); 
								if($vendor_schedule){
									?>
									<div class="import_sync_info_panel">
										<div class="import_sync_info_panel_contents secondary">
											<h4>Want to Remove your next schedule?</h4>
											<form action="" method="post">
												<input type="hidden" name="action" value="remove_schedule">
												<input type="hidden" name="type" value="products">
												<div class="clean_item_sync_action">
													<a href="javascript:void(0)" data-trigger="remove_schedules" class="cancel_sync_button">Remove with next sync</a>
												</div>
											</form>
										</div>
									</div>
								<?php }  ?>
							</div>
						</div>
						<div id="wcfm-coupons_processing" class="dataTables_processing" style="display: none;">
							<div class="process_text">Processing...</div>
						</div>
						<script type="text/javascript">
							jQuery(document).ready(function($){
								$('[data-trigger="cancel_importing"]').click(function(){
									$(this).closest('form').submit();
								});
								$('[data-trigger="remove_schedules"]').click(function(){
									$(this).closest('form').submit();
								});
							});
						</script>
						<script src="https://js.pusher.com/7.0/pusher.min.js"></script>
						<script type="text/javascript">
							var user_id = '<?php echo get_current_user_id(); ?>';
							var pusher = new Pusher('c51db68eb74ee62eb5ef', {
								cluster: 'ap2'
							});
							var channel = pusher.subscribe('fizno');
							channel.bind('importing_'+user_id, function(data) {
								document.getElementById('done').innerText = data.done;
								document.getElementById('total_products').innerText = data.total;
								document.getElementById('progress_bar').style.width = data.percentage+'%';
								document.getElementsByTagName('title')[0].innerText = data.done+' of '+ data.total+ ' - ' +Math.ceil(data.percentage)+'%';
								document.getElementsByClassName('progress_text')[0].innerText=Math.ceil(data.percentage)+'%';
							});
							channel.bind('progress_start_'+user_id, function(data) {
								document.getElementsByClassName("queued")[0].remove();
								if (document.contains(document.querySelector('#wcfm_eBay_vendor_form_expander .alert.notify'))) {
									document.querySelector('#wcfm_eBay_vendor_form_expander .alert.notify').remove();
								}
								var progressive_bar_wrapper = '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" id="progress_bar" style="width:0%"><div class="progress_text">0%</div></div></div><div><span><b><span id="done">0</span> of <span id="total_products">'+data.total+'</span> unique listings</b></span></div>';
								var list = document.getElementById("ebay_import_form");
								list.insertAdjacentHTML('beforeBegin',progressive_bar_wrapper);
							});
							channel.bind('completed_importing_'+user_id, function(data) {
								document.getElementsByTagName('title')[0].innerText = data.msg;
								document.getElementById('progress_bar').classList.add('bg-success');
								document.getElementById('progress_bar').innerHTML='<div class="progress_text">'+data.msg+'</div>'
								// setTimeout(function(){
								// 	window.location.reload(true);
								// }, 5000);
							});
							channel.bind('cancelled_request_'+user_id, function(data) {
								document.getElementsByTagName('title')[0].innerText = data.msg;
								document.getElementById('progress_bar').classList.add('bg-danger');
								document.getElementById('progress_bar').innerHTML='<div class="progress_text">Cancelled</div>';
							});
							channel.bind('location_redirect_'+user_id, function(data) {
								window.location.reload(data.url);
							});
						</script>
						<?php return true; ?>
					<?php } ?>
					<div>
						<h3>ebay Account</h3>
						<div class="ebay_auth_list">
							<table class="accounts_table">
								<tbody>
									<?php do_action('get_ebay_seller_lists'); ?>
								</tbody>
							</table>
							<div>
								<a href="?purpose=import" class="authorized_button">Connect another eBay account</a>
							</div>
						</div>
					</div>
					<div class="item_import_form" id="item_import_form">
						<form action=""  id="ebay_ie_product_setting_form" method="post">
							<input type="hidden" name="action" value="add_wcfm_ie_setting_save">
							<div class="item_import_form_inner">
								<h3 class="section_title">eBay Import Settings</h3>
								<div class="all_import_options">
									<div class="basic_options">
										<div class="import_option_row">
											<div aria-hidden="true" class="import_option_description">
												Remove items
											</div>
											<div class="import_option_contents">
												<div class="import_option_content">
													<div class="import_option_content_setting">
														<div class="option">
															<fieldset class="sold_items">
																<legend class="sr-only">Handling sold items</legend>
																<ul>
																	<li>
																		<label>
																			<input type="radio" value="remove" name="ebay_item_importer[sold_items]" id="ebay_item_importer_sold_items_remove" <?php echo ($form_setting_sold_items=='remove'?'checked="checked"':''); ?> >
																			Remove all items that were removed, expired, or ended for any reason on eBay
																		</label>
																	</li>
																	<li>
																		<label>
																			<input type="radio" value="remove_sold" name="ebay_item_importer[sold_items]" id="ebay_item_importer_sold_items_remove_sold" <?php echo ($form_setting_sold_items=='remove_sold'?'checked="checked"':''); ?>>
																			Only remove items that were sold or ended on eBay (do not remove when expired)
																		</label>
																	</li>
																	<li>
																		<label>
																			<input type="radio" value="ignore" name="ebay_item_importer[sold_items]" id="ebay_item_importer_sold_items_ignore" <?php echo ($form_setting_sold_items=='ignore'?'checked="checked"':''); ?>>
																			Never remove items
																		</label>
																	</li>
																</ul>
															</fieldset>
														</div>
													</div>
													<div class="import_option_content_elaboration">
														<div class="option_explanation"><a title="Handle Sold Items" class="tooltip text_tip" id="Handle_Sold_Items1" href="javascript:void(0)" data-tip="By default, we keep your items synchronized between your eBay
															and Fizno stores over time. When an item is removed from your
															eBay store for any reason (because it sold, expired, removed by
															the seller, etc.), we remove it from your Fizno store
															automatically so you only sell merchandise that's in stock.">more info</a>
														</div>
													</div>
												</div>
											</div>
										</div>
										<div class="import_option_row">
											<div aria-hidden="true" class="import_option_description">
												All, New, Used and revised items
											</div>
											<div class="import_option_contents">
												<div class="import_option_content">
													<div class="import_option_content_setting">
														<div class="option">
															<fieldset class="revise">
																<legend class="sr-only">New and revised items</legend>
																<ul>
																	<li>
																		<label for="ebay_item_importer_revise_false">
																			<input type="radio" value="false" name="ebay_item_importer[revise]" id="ebay_item_importer_revise_false" <?php echo ($form_setting_revise=='false'?'checked="checked"':''); ?>>
																			Import new items only
																			<span class="imports_faster" id="ebay_item_importer_revise_false">Imports faster</span>
																		</label>
																	</li>
																	<li>
																		<label>
																			<input type="radio" value="true" name="ebay_item_importer[revise]" id="ebay_item_importer_revise_true" <?php echo ($form_setting_revise=='true'?'checked="checked"':''); ?>>
																			Import new and revised items (overwrite all existing items)
																			<div class="import_option_selection_caveat">
																				<i class="fa fa-exclamation-circle inline_icon orange"></i>
																				<em>This is a one-time selection. It will only apply to your next import.</em>
																			</div>
																		</label>
																	</li>
																</ul>
															</fieldset>
														</div>
													</div>
													<div class="import_option_content_elaboration">
														<div class="option_explanation">
															<a title="New and Revised Items" class="tooltip text_tip" id="New_and_Revised_Items3" href="javascript:void(0)" data-tip="
															By default, Fizno will only import items that don't already exist in your booth.
															If 'import new and revised items' is selected, we will also re-import the items we
															had imported previously. Select this option only if you have made revisions to
															previously imported eBay items, and you want those items to be re-imported.
															<br>
															<br>
															This option may add significant time to your import. Also note if you choose this option,
															it will only apply to your next import. If you have an ongoing sync, you’ll need to reselect it to apply it to future imports.
															">more info</a>
														</div></div>
													</div>
												</div>
											</div>
											<div class="import_option_row">
												<div aria-hidden="true" class="import_option_description">
													Item price and item details 
												</div>
												<div class="import_option_contents">
													<div class="import_option_content_setting">
														<fieldset class="revise">
															<legend class="sr-only">Item price and status</legend>
															<ul>
																<li><div class="import_option_content">
																	<div class="import_option_content_setting">
																		<label  for="ebay_item_importer_discount_price">
																			<input class="main_input" type="checkbox" value="1" <?php echo(isset($form_setting_discount_price)&&$form_setting_discount_price==true?'checked="checked"':''); ?> name="ebay_item_importer[discount_price]" id="ebay_item_importer_discount_price">
																			<label class="dual_input" for="ebay_item_importer_discount_price">
																				Discount prices by
																			</label>
																			<input type="number" name="ebay_item_importer[discount_amount]" id="ebay_item_importer_discount_amount" class="wcfm-import-export-input" value="<?php echo $form_setting_discount_amount; ?>" oninput="this.value|=0">%
																			<span>saved on Fizno.</span>
																		</label>
																	</div>
																</div>
															</li>
															<li>
																<div class="import_option_content">
																	<div class="import_option_content_setting">
																		<input type="checkbox" value="1" name="ebay_item_importer[quantity_update_on_ebay]" id="ebay_item_importer_quantity_update_on_ebay" <?php echo(isset($quantity_update_on_ebay) && $quantity_update_on_ebay?'checked="checked"':''); ?> >
																		<label for="ebay_item_importer_quantity_update_on_ebay">
																			<span>Update the item quantity on eBay after a Fizno sale</span>
																		</label>
																	</div>
																	<div class="import_option_content_elaboration">
																		<div class="option_explanation">
																			<a title="Reduce eBay Quantity" class="tooltip text_tip" id="Import_Unsold_Items31" href="javascript:void(0)" data-tip="
																			This will ensure that you don't double-sell items by decrementing quantity
																			of items on eBay when they sell on Fizno.
																			<br>
																			<br>
																			If you only have one of the item posted on eBay, we will end the listing where eBay policy permits it.
																			<br>
																			<br>
																			Note that we ONLY decrement items on eBay after you have agreed
																			to an offer or received payment on Fizno (i.e., items won't be removed
																			from eBay when a buyer proposes an offer or doesn't complete checkout)
																			">more info</a>
																		</div></div>
																	</div>
																</li>
															<?php /*<li>
																<div class="import_option_content">
																	<div class="import_option_content_setting">
																		<input type="checkbox" value="1" name="ebay_item_importer[import_items_without_bin]" id="ebay_item_importer_import_items_without_bin" <?php echo(isset($form_setting_without_bin)&&$form_setting_without_bin==true?'checked="checked"':''); ?> >
																		<label for="ebay_item_importer_import_items_without_bin">
																			Import auctions without a "Buy it Now" price
																		</label>
																	</div>
																	<div class="import_option_content_elaboration">
																		<div class="option_explanation">
																			<a title="Import Items Without BIN" class="tooltip text_tip" id="Import_Items_Without_BIN15" href="javascript:void(0)" data-tip="
																			By default, Fizno will only import items that have a 'Buy it Now' price.
																			However, if you want to import your items that don't have a BIN price, you
																			can check this option and we will grab those listings as well as your BIN
																			listings.
																			<br>
																			<br>
																			You will then need to set prices for those items after they are imported.
																			">more info</a>
																		</div>
																	</div>
																</div>
															</li> 
															<li>
																<div class="import_option_content">
																	<div class="import_option_content_setting">
																		<input type="checkbox" value="1" name="ebay_item_importer[import_unsold_items]" id="ebay_item_importer_import_unsold_items" <?php echo(isset($form_setting_unsold_items)&&$form_setting_unsold_items==true?'checked="checked"':''); ?>>
																		<label for="ebay_item_importer_import_unsold_items">
																			Import expired items from your "Unsold" folder
																		</label>
																	</div>
																	<div class="import_option_content_elaboration">
																		<div class="option_explanation">
																			<a title="Import Unsold Items" class="tooltip text_tip" id="Import_Unsold_Items31" href="javascript:void(0)" data-tip="
																			By default, Fizno will only import items that are actively for sale.
																			Check this option to import items that expired in the last 60 days without
																			being sold. We're unable to import ended listings from your 'Unsold' folder.
																			Those listings will be skipped.
																			">more info</a>
																		</div>
																	</div>
																</div>
																</li> */ ?>
															</ul>
														</label>
													</fieldset>
												</div>
											</div>
										</div>
										<?php /* <a class="show_advanced_options" href="javascript:void(0)">Show more import options</a>
										<div class="advanced_options">
											<a class="hide_advanced_options" href="javascript:void(0)">Hide advanced options</a>
											<div class="import_option_row">
												<div class="import_option_description">
													Item details and syncing
												</div>
												<div class="import_option_contents">
													<div class="import_option_content_setting">
														<div class="import_option_content">
															<div class="import_option_content_setting">
																<input type="checkbox" value="1" name="ebay_item_importer[import_feedback]" id="ebay_item_importer_import_feedback" <?php echo(isset($form_setting_import_feedback) && $form_setting_import_feedback?'checked="checked"':''); ?> >
																<label for="ebay_item_importer_import_feedback">
																	<span>Include eBay item feedback</span>
																</label>
															</div>
															<div class="import_option_content_elaboration">
																<div class="option_explanation">
																	<a title="Import eBay Feedback" class="tooltip text_tip" id="Import_Unsold_Items31" href="javascript:void(0)" data-tip="
																	Select this option to import your overall feedback counts. Note that these numbers are estimated
																	from overall feedback count and percentages, so they may not be exact (but the percentage will be).
																	">more info</a>
																</div></div>
															</div>
															<ul>
																<li>
																	<div class="import_option_content">
																		<div class="import_option_content_setting">
																			<input type="checkbox" value="1" name="ebay_item_importer[quantity_update_on_ebay]" id="ebay_item_importer_quantity_update_on_ebay" <?php echo(isset($quantity_update_on_ebay) && $quantity_update_on_ebay?'checked="checked"':''); ?> >
																			<label for="ebay_item_importer_quantity_update_on_ebay">
																				<span>Update the item quantity on eBay after a Fizno sale</span>
																			</label>
																		</div>
																		<div class="import_option_content_elaboration">
																			<div class="option_explanation">
																				<a title="Reduce eBay Quantity" class="tooltip text_tip" id="Import_Unsold_Items31" href="javascript:void(0)" data-tip="
																				This will ensure that you don't double-sell items by decrementing quantity
																				of items on eBay when they sell on Fizno.
																				<br>
																				<br>
																				If you only have one of the item posted on eBay, we will end the listing where eBay policy permits it.
																				<br>
																				<br>
																				Note that we ONLY decrement items on eBay after you have agreed
																				to an offer or received payment on Fizno (i.e., items won't be removed
																				from eBay when a buyer proposes an offer or doesn't complete checkout)
																				">more info</a>
																			</div></div>
																		</div>
																	</li>
																	<li>
																		<div class="import_option_content">
																			<div class="import_option_content_setting">
																				<input class="main_input" type="checkbox" value="1" name="ebay_item_importer[synchronize]" id="ebay_item_importer_synchronize" <?php echo(isset($days_to_activate)?'checked="checked"':''); ?>  >
																				<label class="dual_input" for="ebay_item_importer_synchronize">
																					<span>Sync your booth with eBay and</span>
																					<select class="sub_input" name="ebay_item_importer[days_to_activate]" id="ebay_item_importer_days_to_activate">
																						<option value="0" <?php echo(isset($days_to_activate) && !$days_to_activate?'selected="selected"':''); ?> >don't wait</option>
																						<option value="1" <?php echo(isset($days_to_activate) && $days_to_activate==1?'selected="selected"':''); ?> >wait 1 day</option>
																						<option value="3" <?php echo(isset($days_to_activate) && $days_to_activate==3?'selected="selected"':''); ?>>wait 3 days</option>
																						<option value="5" <?php echo(isset($days_to_activate) && $days_to_activate==5?'selected="selected"':''); ?>>wait 5 days</option>
																					</select>
																					<span>to post any new items</span>
																				</label>
																			</div>
																			<div class="import_option_content_elaboration">
																				<div class="option_explanation">
																					<a title="Synchronize and Delay" class="tooltip text_tip" id="Import_Unsold_Items31" href="javascript:void(0)" data-tip="
																					This will sync your Fizno booth with your eBay account. This option will delete any eBay items you previously imported unless they are still in your eBay store. Any items that you created on Fizno will not be deleted.
																					<p>
																						You can also choose to delay any new items we receive from eBay.
																						Some sellers want to review their imported listings before those items are
																						put up for sale on Fizno. This option lets you control how long
																						newly-imported items will sit in your store before we post them live for
																						buyers to purchase.
																					</p>
																					<p>
																						During this time, you can edit or delete imported items. You can also visit your booth
																						and click the 'Update' button to manually activate imported items.
																					</p>
																					">more info</a>
																				</div></div>
																			</div>
																		</li>
																	</ul>
																</div>
															</div>
														</div>
														</div> */ ?>
													</div>
												</div>
												<input type="submit" name="commit" <?php echo (!geteBayVendors()?'disabled="disabled" title="Connect atleast one account before importing"':'') ?> value="Start your eBay import" class="button_large submit_import_job" data-disable-with="Start your eBay import">
											</form>
										</div>
									</div>
									<div id="wcfm-coupons_processing" class="dataTables_processing" style="display: none;">
										<div class="process_text">Processing...</div>
									</div>
									<div class="wcfm-clearfix"></div>
									<br>
									<div id="error_response">
									</div>
								</div>
								<div class="wcfm-clearfix"></div>
							</div>
							<div class="wcfm-clearfix"></div>
						</div>
					</div>
			<?php /* <script type="text/javascript">
				jQuery(document).ready(function($){
					$('.show_advanced_options').click(function(){
						if($(this).next('.advanced_options').hasClass('hide')){
							$(this).next('.advanced_options').removeClass('hide');
							$(this).addClass('hide');
						}
					});
					$('.hide_advanced_options').click(function(){
						if($('.show_advanced_options').hasClass('hide')){
							$('.show_advanced_options').removeClass('hide');
							$('.advanced_options').addClass('hide');
						}
					});
				});
			</script> *?>