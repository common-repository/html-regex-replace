<?php
/*
Plugin Name: HTML Regex Replace
Plugin URI: http://wp-regrep.blogspot.com
Description: Replace any html you write in editor (Visual or HTML) with pre-defined string. Use Regexp to define patterns for replacement.
Version: 1.1
Author: Nick Lugovskoy
Author URI: http://www.cirux.ru

Released under the GPL v.2, http://www.gnu.org/copyleft/gpl.html

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

// Register plugin
if ( ! function_exists('tm_regex_load_plugin') ) {
	function tm_regex_load_plugin($plug) {
            $plug["tm_regex"] = plugin_dir_url(__FILE__).'/mce_plugin_regex.js?rr='.rand();
            return $plug;
	}
	add_filter( 'mce_external_plugins', 'tm_regex_load_plugin', 999 );
}

function tm_regex_write_js($ptrns, $repls) {
    $fname = plugin_dir_path(__FILE__)."/mce_plugin_regex.js";
    $fp    = fopen($fname, 'w') or trigger_error('Cannot create file: '.$fname, E_USER_ERROR);

    $js_text1 = "
(function() {
    tinymce.create(\"tinymce.plugins.TMRegex\", {
        init : function(ed, url) {
            ed.onSaveContent.add(function(ed, o) {";
    $js_text_regex1 = "
                o.content = o.content.replace(";
    $js_text_regex2 = ");";
    $js_text2 = "
            });
        }
    });
    tinymce.PluginManager.add('tm_regex', tinymce.plugins.TMRegex);
})();";

    fwrite($fp, $js_text1);
    if ( ! empty($ptrns)) {
        foreach ($ptrns as $idx=>$ptrn) {
            $repl = $repls[$idx];

            if ( ! empty($ptrn)) {
                fwrite($fp, $js_text_regex1);

                if (preg_match("/^\/.*\/[gi]{0,2}$/", $ptrn)) {
                    fwrite($fp, $ptrn.', "'.$repl.'"');
                }
                else {
                    fwrite($fp, '/'.$ptrn.'/g, "'.$repl.'"');
                }

                fwrite($fp, $js_text_regex2);
            }
        }
    }
    fwrite($fp, $js_text2);
    fclose($fp);
}

// Process options
if ( ! function_exists('tm_regex_load_vars') ) {
    function tm_regex_load_vars() {
        $tm_regex_options = get_option('tm_regex_options');
        if ( ! empty($tm_regex_options) ) {
            return;
        }

        $tm_regex_options = array();
        add_option( 'tm_regex_options', $tm_regex_options );
    }
    add_action( 'admin_init', 'tm_regex_load_vars' );
}

// Plugin settings page
if ( ! function_exists('tm_regex_plugin_menu') ) {
    function tm_regex_plugin_menu() {
        add_options_page('HTML Regex Replace', 'HTML Regex Replace',
                         'manage_options', 'tm_regex-settings', 'tm_regex_plugin_options');
    }

    add_action('admin_menu', 'tm_regex_plugin_menu');

    function tm_regex_plugin_options() {
        if ( ! current_user_can('manage_options') ) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        $hidden_field_name = 'mt_submit_hidden';

        if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
            // Read their posted value
            array_walk_recursive($_POST, create_function('&$val', '$val = stripslashes($val);'));
            foreach ($_POST as $var=>$val) {
                if (preg_match("/opt_([0-9]+)_ptrn/", $var, $matches)) {
                    $tm_regex_ptrns[$matches[1]] = $val;
                }
                if (preg_match("/opt_([0-9]+)_repl/", $var, $matches)) {
                    $tm_regex_repls[$matches[1]] = $val;
                }
            }
            
            // Save the posted value in the database
            update_option( 'tm_regex_opt_ptrns', $tm_regex_ptrns );
            update_option( 'tm_regex_opt_repls', $tm_regex_repls );
            tm_regex_write_js($tm_regex_ptrns, $tm_regex_repls);
            add_filter( 'mce_external_plugins', 'tm_regex_load_plugin', 999 );

            // Put an settings updated message on the screen
?>
<div class="updated"><p><strong><?php _e('Settings saved.', 'menu-test' ); ?></strong></p></div>
<?php
        }
        else {
            $tm_regex_ptrns = get_option('tm_regex_opt_ptrns');
            $tm_regex_repls = get_option('tm_regex_opt_repls');
        }
        echo '<div class="wrap">';
        echo "<h2>" . __( 'HTML Regex Replace Settings', 'menu-test' ) . "</h2>";
?>
<script>

var N = <?php 
if ( ! empty($tm_regex_ptrns)) {
    echo max(array_keys($tm_regex_ptrns));
} else {
    echo 1;
}
?>;

function add_field() {
var wrapper = document.createElement("div");
var ptrn_el = document.createElement("input");
var repl_el = document.createElement("input");
var desc = document.createElement("span");
var del_btn = document.createElement("input");

N += 1;

wrapper.setAttribute("id", "rec-"+N);

ptrn_el.setAttribute("type", "text");
ptrn_el.setAttribute("size", "80");
ptrn_el.setAttribute("value", "");
ptrn_el.setAttribute("name", "opt_"+N+"_ptrn");

repl_el.setAttribute("type", "text");
repl_el.setAttribute("size", "80");
repl_el.setAttribute("value", "");
repl_el.setAttribute("name", "opt_"+N+"_repl");

del_btn.setAttribute("type", "button");
del_btn.setAttribute("value", "Delete");
del_btn.setAttribute("onclick", "del_field("+N+")");

wrapper.innerHTML = "Regexp: ";
desc.innerHTML = " New string: ";

var d=document.getElementById("tm_inputs");

wrapper.appendChild(ptrn_el);
wrapper.appendChild(desc);
wrapper.appendChild(repl_el);
wrapper.appendChild(del_btn);

d.appendChild(wrapper);
}

function del_field(idx) {
var el = document.getElementById("rec-"+idx);
el.parentNode.removeChild(el);

}

</script>
    <form name="form1" method="post" action="">
        <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
        <br/>
        <input type="button" value="Add new replace pattern" onclick="add_field()"/>
        <br/>
        <br/>
        <div id="tm_inputs">
<?php
        if ( ! empty($tm_regex_ptrns)) {
            foreach ($tm_regex_ptrns as $idx=>$ptrn) {
                $repl = $tm_regex_repls[$idx];
                if (empty($repl)) {
                    $repl = "";
                }

                echo '<div id="rec-'.$idx.'">Regexp: <input type="text" value="'.$ptrn.'" size="80" name="opt_'.$idx.'_ptrn"/> New string: <input type="text" value="'.$repl.'" size="80" name="opt_'.$idx.'_repl"/><input type="button" value="Delete" onclick="del_field('.$idx.')"/></div>';
            }
        }
?>
        </div><hr />

        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
        </p>
    </form>
    <br/>
    <div>
<h2>A little bit of help</h2>
<p>Use <input type="button" value="Add new replace pattern"/> to add new replacement rule or press <input type="button" value="Delete"/> to remove it.</p>
<p><b>Regexp</b> is a regular expression or simply a string that will be found in post/page html and replaced with <b>New string</b>.</p>
<p>You can use different ways to specify <b>Regexp</b>:
<ul>
<li><code>puh</code> → find all instances of 'puh'</li>
<li><code>\(c\)</code> → find all instances of '(c)'</li>
<li><code>(Maman|Papan) is [t]*here</code> → find 'Maman is here' or 'Papan is there' or ... </li>
<li><code>/Bar/gi</code> → find 'bar' globally and ignoring case</li>
</ul>

See <a href="http://www.w3schools.com/jsref/jsref_obj_regexp.asp" target="_blank" title="JavaScript Regular Expression description.">JavaScript RegExp Object</a> for better regular expression description.
</p>
    </div>
</div>
<?php
    }
}

