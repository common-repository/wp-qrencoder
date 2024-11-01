<?php
/*
Plugin Name: WP-QREncoder
Plugin URI:  http://www.werxltd.com/wp/portfolio/wordpress-qrencoder-plugin/
Version: 0.1
Description: Allows a user to easily add QR encoded data to a post.
Author: Wes widner
Author URI: http://www.werxltd.com
*/

/*
 * This file is part of WP-QREncoder a plugin for Word Press
 * Copyright (C) 2010 Wes Widner
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// Some important constants
define('WP_QRENCODER_OPEN', " (((");  //You can change this if you really have to, but I wouldn't recommend it.
define('WP_QRENCODER_CLOSE', ")))");  //Same with this one.
define('WP_QRENCODER_VERSION', '0.1');

// Instantiate the class 
$werx_wp_qrencoder = new werx_wp_qrencoder();

// Encapsulate in a class
class werx_wp_qrencoder {
	var $current_options;
	var $default_options;
	
	/**
	 * Constructor.
	 */
	function werx_wp_qrencoder() {		
	
		// Define the implemented option styles		
		$this->error_levels = array(
			'L' => 'Low',
			'M' => 'Medium',
			'Q' => 'Q?',
			'H' => 'High'
		);
		
		$this->image_types = array(
			'other' => 'PNG',
			'J' => 'JPG'
		);
		
		$this->qrversions = array();
		for($i = 1; $i <= 40; $i++) { $this->qrversions[$i] = $i; }
		
		// Define default options
		$this->default_options = array('error_level'=>'M',
									  'module_size'=>4,
									  'qrversion'=>null,
									  'imagetype'=>'other',
									  'version'=>WP_QRENCODER_VERSION);
		
		// Get the current settings or setup some defaults if needed
		if (!$this->current_options = get_option('werx_qrencoder_options')){
			$this->current_options = $this->default_options;
			update_option('werx_qrencoder_options', $this->current_options);
		} else { 
			
			// Set any unset options
			if ($this->current_options['version'] != WP_QRENCODER_VERSION) {
				foreach ($this->default_options as $key => $value) {
					if (!isset($this->current_options[$key])) {
						$this->current_options[$key] = $value;
					}
				}
				$this->current_options['version'] = WP_QRENCODER_VERSION;
				update_option('werx_qrencoder_options', $this->current_options);
			}
		}
		
		if (!empty($_POST['save_options'])){
			//$qrencoder_options['superscript'] = (array_key_exists('superscript', $_POST)) ? true : false;
		
			$qrencoder_options['error_level'] = $_POST['error_level'];
			$qrencoder_options['module_size'] = $_POST['module_size'];
			$qrencoder_options['imagetype'] = $_POST['imagetype'];
			
			if($_POST['qrversion'] < 40 && $_POST['qrversion'] > 0) {
				$qrencoder_options['qrversion'] =  $_POST['qrversion']; 
			}
			
			
			
			update_option('werx_qrencoder_options', $qrencoder_options);
		}elseif(!empty($_POST['reset_options'])){
			update_option('werx_qrencoder_options', '');
			update_option('werx_qrencoder_options', $this->default_options);
		}
		
		// Hook me up
		add_action('the_content', array($this, 'process'), $this->current_options['priority']);
		add_action('admin_menu', array($this, 'add_options_page')); 		// Insert the Admin panel.
		add_action('wp_head', array($this, 'insert_styles'));
	}
	
	
	/**
	 * Searches the text and extracts qrencoder. 
	 * Adds the identifier links and creats qrencoder list.
	 * @param $data string The content of the post.
	 * @return string The new content with qrencoder generated.
	 */
	function process($data) {
		global $post;
		
		// Regex extraction of all qrencoder (or return if there are none)
		if (!preg_match_all("/(\(\(\(|<qrencoder>)(.*)(\)\)\)|<\/qrencoder>)/Us", $data, $identifiers, PREG_SET_ORDER)) {
			return $data;
		}
		
		$qrencoder = array();
		
		// Create 'em
		for ($i=0; $i<count($identifiers); $i++){
			// Look for ref: and replace in identifiers array.
			if (substr($identifiers[$i][2],0,4) == 'ref:'){
				$ref = (int)substr($identifiers[$i][2],4);
				$identifiers[$i]['text'] = $identifiers[$ref-1][2];
			}else{
				$identifiers[$i]['text'] = $identifiers[$i][2];
			}
		}
		
		// Display qr codes
		foreach ($identifiers as $key => $value) {
			
			$my_dir = str_replace(WP_PLUGIN_DIR, '', dirname(__FILE__));
			
			$imgsrc = WP_PLUGIN_URL.$my_dir."/qr_img.php?d={$value['text']}";
			
			if(!is_null($this->current_options['imagetype'])) $imgsrc .= "&t={$this->current_options['imagetype']}";
			if(!is_null($this->current_options['qrversion'])) $imgsrc .= "&v={$this->current_options['qrversion']}";
			if(!is_null($this->current_options['module_size'])) $imgsrc .= "&s={$this->current_options['module_size']}";
			if(!is_null($this->current_options['error_level'])) $imgsrc .= "&s={$this->current_options['error_level']}";
			
			$id_replace = "<img src=\"{$imgsrc}\" />";
			$data = substr_replace($data, $id_replace, strpos($data,$value[0]),strlen($value[0]));
			//$data = substr_replace($data, '', strpos($data,$value[0]),strlen($value[0]));
		}
		
		
		return $data;
	}
	
	/**
	 * Really insert the options page.
	 */
	function qrencoder_options_page() { 
		$this->current_options = get_option('werx_qrencoder_options');
		foreach ($this->current_options as $key=>$setting) {
			$new_setting[$key] = htmlentities($setting);
		}
		$this->current_options = $new_setting;
		unset($new_setting);
?>

<?php if (!empty($_POST['save_options'])): ?>
<div class="updated"><p><strong>Options saved.</strong></p></div>
<?php elseif (!empty($_POST['reset_options'])): ?>
<div class="updated"><p><strong>Options reset.</strong></p></div>
<?php endif; ?>

<div class="wrap">
	<h2>WP-QREncoder Options</h2>
	<div id="qrencoder-options-sidebar" style="float:right; width:220px;" class="side-info submitbox">
		<div  style="background-color:#EAF3FA;">
			<div id="previewview"> </div>
			<div style="padding:0 8px;">
				<h5>Bug Reports / Feature Requests</h5>
				<p>You should report any bugs you find and submit feature requests to <a href="http://bugs.werxltd.com">our in-house bug tracker</a>.</p>
			</div>
			<!--div class="submit">
				<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
					<input type="hidden" name="cmd" value="_s-xclick" />
					<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but04.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!" />
					<img alt="" border="0" src="https://www.paypal.com/en_AU/i/scr/pixel.gif" width="1" height="1" style="display:block; margin:auto;" />
					<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----" />
				</form>
			</div-->
		</div>
		<div>
			<h5>Documentation</h5>
			<p>You can view <a href="http://werxltd.com/wp/portfolio/wordpress-qrencoder-plugin/" title="WP-QREncoder documentation">the documentation</a> at <a href="http://www.werxltd.com" title="Wes Widner">Wes Widner</a>, the author's website.</p>
			<h5>Licensing Info</h5>
			<p>WP-QREncoder, Copyright &copy; 2010 Wes Widner</p>
			<p>WP-QREncoder is licensed under the <a href="http://www.gnu.org/licenses/gpl.html">GNU GPL</a>. WP-QREncoder comes with ABSOLUTELY NO WARRANTY. This is free software, and you are welcome to redistribute it under certain conditions. See the <a href="http://www.gnu.org/licenses/gpl.html">license</a> for details.</p>
			
		</div>
	</div>
	<div id="qrencoder-options" style="margin-right:230px;">
		<form method="post">
			<h3>QR Encoding Options</h3>
			<fieldset style="border:none; border-bottom:solid 8px #fff; line-height:20px; margin-bottom:9px; padding:10px; background:#EAF3FA;">
				<table>
					<tr>
						<th><label>Setting</label></th>
						<th><label>Value</label></th>
						<th><label>Description</label></th>
					</tr>
					<tr>
						<td>Error Correction Level</td>
						<td>
							<select name="error_level" id="error_level">
								<?php foreach ($this->error_levels as $key => $val): ?>
								<option value="<?php echo $key; ?>" <?php if ($this->current_options['error_level'] == $key) echo 'selected="selected"'; ?> ><?php echo $val; ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>Error correction level to build into generated QR code image</td>
					</tr>
					<tr>
						<td>Module Size</td>
						<td><input type="text" id="module_size" name="module_size" size="3" value="<?php echo $this->current_options['module_size']; ?>" /></td>
						<td>Size of QR code to generate</td>
					</tr>
					<tr>
						<td>QR Version</td>
						<td><input type="text" id="qrversion" name="qrversion" size="3" value="<?php echo $this->current_options['qrversion']; ?>" /></td>
						<td>QR version [1-40] to use</td>
					</tr>
					<tr>
						<td>Image Type</td>
						<td>
							<select name="imagetype" id="imagetype">
								<?php foreach ($this->image_types as $key => $val): ?>
								<option value="<?php echo $key; ?>" <?php if ($this->current_options['imagetype'] == $key) echo 'selected="selected"'; ?> ><?php echo $val; ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>Type of image to generate</td>
					</tr>
				</table>
			</fieldset>
			<p class="submit"><input type="submit" name="reset_options" value="Reset Options to Defaults" /> <input type="submit" name="save_options" value="Update Options &raquo;" /></p>
		</form>
	</div>
</div>
	
<?php
	}
	
	/**
	 * Insert the options page into the admin area.
	 */
	function add_options_page() {
		// Add a new menu under Options:
		add_options_page('wp-qrencoder', 'wp-qrencoder', 8, __FILE__, array($this, 'qrencoder_options_page'));
	}
	
	
	function upgrade_post($data){
		$data = str_replace('<qrencoder>',WP_QRENCODER_OPEN,$data);
		$data = str_replace('</qrencoder>',WP_QRENCODER_CLOSE,$data);
		return $data;
	}
	
	function insert_styles(){
		?>
		<style type="text/css">
			<?php if ($this->current_options['list_style_type'] != 'symbol'): ?>
			ol.qrencoder li {list-style-type:<?php echo $this->current_options['list_style_type']; ?>;}
			<?php endif; ?>
			<?php echo $this->current_options['style_rules'];?>
		</style>
		<?php
	}
}
