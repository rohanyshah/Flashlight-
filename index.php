<?php   require_once("../../../config.php");

	//who's logged in? loggedInUserGuid will be an empty string if no user is logged in...
	$loggedInUserGuid = "";
	if(isset($_COOKIE[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_COOKIE[APP_LOGGEDIN_COOKIE_NAME]);
	if($loggedInUserGuid == ""){
		if(isset($_SESSION[APP_LOGGEDIN_COOKIE_NAME])) $loggedInUserGuid = fnFormInput($_SESSION[APP_LOGGEDIN_COOKIE_NAME]);
	}
			
	//init user object for the logged in user (we may not have a logged in user)...
	$objLoggedInUser = new User($loggedInUserGuid);
	
	//if loggedInUserGuid is empty, kick out the user...
	$objLoggedInUser->fnLoggedInReq($loggedInUserGuid);
	
	//if we do have a logged in user, update their "last page view"...
	$objLoggedInUser->fnUpdateLastRequest($loggedInUserGuid);

	//plugins table constant is different on self hosted servers..
	if(!defined("TBL_PLUGINS")){
		if(defined("TBL_BT_PLUGINS")){
			define("TBL_PLUGINS", TBL_BT_PLUGINS);
		}
	}


	//////////////////////////////////////////////////////////////////////////////
	/*
		The includePath and controlPanelURL variables in this script is used in the HTML on this page to
		account for differences between Self Hosted control panels and buzztouch.com control panels.
	*/
	$includePath = "";
	$controlPanelURL = "";
	if(defined("APP_CURRENT_VERSION")){
		$includePath = rtrim(APP_PHYSICAL_PATH, "/") . "/bt_v15/bt_includes";
		$controlPanelURL = "../../../bt_v15/bt_app";
	}else{
		$includePath = rtrim(APP_PHYSICAL_PATH, "/") . "/app/cp_v20/bt_includes";
		$controlPanelURL = "../../../app/cp_v20/bt_app";
	}
	//////////////////////////////////////////////////////////////////////////////
	
	//the Page class is used to construct all the HTML pages in the control panel...
	$objControlPanelWebpage = new Page();
	
	//the guid of the app comes from the URL (if a GET request) or the hidden form field (if a POST request)...
	$appGuid = fnGetReqVal("appGuid", "", $myRequestVars);
	
	//the guid of the BT_itemId comes from the URL (if a GET request) or the hidden form field (if a POST request)...
	$BT_itemId = fnGetReqVal("BT_itemId", "", $myRequestVars);

	//need an appGuid and a BT_itemId...
	if($appGuid == "" || $BT_itemId == ""){
		echo "invalid request";
		exit();
	}else{
		
		//init the App object using this app's guid...
		$objApp = new App($appGuid);
		
		//the the page object to use the app's name in the webpage's title...
		$objControlPanelWebpage->pageTitle = $objApp->infoArray["name"] . " Control Panel";

		//init the BT_item (the screen object) using this screen's guid...
		$objBT_item = new Bt_item($BT_itemId);
		
		//we need the uniquePluginId for this plugin, it's used in the inc_pluginDetails.php file (included lower in this file's HTML)...
		$uniquePluginId = $objBT_item->infoArray["uniquePluginId"];

		//we need the jsonVars for this plugin, they are used in the advanced properties (included lower in this file's HTML)...
		$jsonVars = $objBT_item->infoArray["jsonVars"];

		//we need the nickname for this plugin, it is used in the advanced properties (included lower in this file's HTML)...
		$nickname = $objBT_item->infoArray["nickname"];

		//make sure the person that is logged in has the privilege to manage this app....
		if($objApp->fnCanManageApp($loggedInUserGuid, $objLoggedInUser->infoArray["userType"], $appGuid, $objApp->infoArray["ownerGuid"])){
			//all good, fnCanManageApp will end exectuion if invalid..
		}
		
	}
	
	
	///////////////////////////////////////////////////////////////////////////////
	//from previous screen so back button maintains sorting / paging / searching
	$sortUpDown = fnGetReqVal("sortUpDown", "DESC", $myRequestVars);
	$sortColumn = fnGetReqVal("sortColumn", "", $myRequestVars);
	$currentPage = fnGetReqVal("currentPage", "1", $myRequestVars);
	$viewStyle = fnGetReqVal("viewStyle", "gridView", $myRequestVars);
	$search = fnGetReqVal("searchInput", "search...", $myRequestVars);
	$searchPluginTypeUniqueId = fnGetReqVal("searchPluginTypeUniqueId", "search...", $myRequestVars);

	//querystring for links so user can "go back" and without losing paging / sorting / filtering variables...
	$qVars = "&searchInput=" . fnFormOutput($search) . "&searchPluginTypeUniqueId=" . $searchPluginTypeUniqueId;
	$qVars .= "&sortColumn=" . $sortColumn . "&sortUpDown=" . $sortUpDown . "&currentPage=" . $currentPage;
	$qVars .= "&viewStyle=" . $viewStyle;
	///////////////////////////////////////////////////////////////////////////////

	//ask the Page object to print the html to produce the control panel's webpage...
	echo $objControlPanelWebpage->fnGetPageHeaders();
	echo $objControlPanelWebpage->fnGetBodyStart();
	echo $objControlPanelWebpage->fnGetTopNavBar($loggedInUserGuid);
	
?>

<!--do not remove these hidden form fields, they are required for the control panel -->
<input type="hidden" name="appGuid" id="appGuid" value="<?php echo $appGuid;?>">
<input type="hidden" name="BT_itemId" id="BT_itemId" value="<?php echo $BT_itemId;?>">
<input type="hidden" name="sortUpDown" id="sortUpDown" value="<?php echo $sortUpDown;?>">
<input type="hidden" name="sortColumn" id="sortColumn" value="<?php echo $sortColumn;?>">
<input type="hidden" name="currentPage" id="currentPage" value="<?php echo $currentPage;?>">
<input type="hidden" name="viewStyle" id="viewStyle" value="<?php echo $viewStyle;?>">
<input type="hidden" name="search" id="search" value="<?php echo fnFormOutput($search);?>">
<input type="hidden" name="searchPluginTypeUniqueId" id="searchPluginTypeUniqueId" value="<?php echo $searchPluginTypeUniqueId;?>">

<!--load the jQuery files using the Google API -->
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js"></script>

<script>

	<!--shows or hides advanced property section -->
	function fnExpandCollapse(hideOrExpandElementId){
		var theBoxToExpandOrCollapse = document.getElementById(hideOrExpandElementId);
		if(theBoxToExpandOrCollapse.style.display == "none"){
			theBoxToExpandOrCollapse.style.display = "block";
		}else{
			theBoxToExpandOrCollapse.style.display = "none";
		}
	}
	
	<!--saves advanced property -->
	function saveAdvancedProperty(showResultsInElementId){
	
		//hide all the previous "saved result" messages...
		var divs = document.getElementsByTagName('div');
		for(var i = 0; i < divs.length; i++){ 
			if(divs[i].id.indexOf("saveResult_", 0) > -1){
				divs[i].innerHTML = "&nbsp;";
			}
		} 	
	
		//show working message in the appropriate <html> element...
		resultsDiv = document.getElementById(showResultsInElementId)
		resultsDiv.innerHTML = "saving entries...";
		resultsDiv.className = "submit_working";
		
		//make sure jQuery is loaded...
		if(jQuery) {  
			
			//vars for the form, it's fields, and it's values...
			var $form = $("#frmMain"),
			
			//select and cache all form fields...
			$inputs = $form.find("input, select, button, textarea"),
			
			//serialize the data in the form...
			serializedData = $form.serialize();
		
			//disable the inputs while we wait for results...
			$inputs.attr("disabled", "disabled");
			
			//POST the ajax request...
		   $.ajax({
				url: "save_JSON.php",
				type: "post",
				data: serializedData,
				
				//function that will be called on success...
				success:function(response, textStatus, jqXHR){
					
					//show response...
					if(response == "invalid request"){
						resultsDiv.className = "submit_working";
					}else{
						resultsDiv.className = "submit_done";
					}
					resultsDiv.innerHTML = response;
				
				},
				
				//function that will be called on error...
				error: function(jqXHR, textStatus, errorThrown){
					resultsDiv.className = "submit_working";
					resultsDiv.innerHTML = "A problem occurred while saving the entries (2)";
				},
				
				//function that will be called on completion (success or error)...
				complete: function(){
					$inputs.removeAttr("disabled");
				}
			});		  
		
		}else{
			//jQuery is not loaded?
			resultsDiv.innerHTML = "jQuery not loaded?";
		}		
		
		
		
	}
	


</script>

<div class='content'>
        
    <fieldset class='colorLightBg'>

        <!-- app control panel navigation--> 
        <div class='pluginNav'>
            <span style='white-space:nowrap;'><a href="<?php echo $controlPanelURL . "/?appGuid=" . $appGuid;?>" title="Application Control Panel">Application Home</a></span>
            &nbsp;&nbsp;|&nbsp;&nbsp;
			<?php echo $objControlPanelWebpage->fnGetControlPanelLinks("application", $appGuid, "inline", $viewStyle); ?>
        </div>

       	<div class='pluginContent colorDarkBg minHeight'>
               
            <!-- breadcrumbs back to screens and actions are created in this include file -->
            <div class='contentBand colorBandBg' style='font-size:10pt;'>
            	<?php include($includePath . "/inc_screenBreadcrumbs.php");?>
            </div>

            <!--plugin icon and details on right are created in this include file-->
            <div class="pluginRight colorLightBg">
                <?php include($includePath . "/inc_pluginDetails.php");?>                    
            </div>
                

            <!--screen / action properties on left-->
            <div class="pluginLeft minHeight">

                <div style='margin:10px;margin-left:0px;margin-bottom:20px;'>   
                    <b>Flashlight Feature</b>
                    
                    <div style='padding-top:5px;'>
                    	Change the button that activates the flashlight by changing the image in 
                        your sourcecode plugin files.
                    </div>
                    <div style="padding-top:5px;">
                    	See more goodies at buzztouchmods.com, for support on this plugin, 
                        please send a private message to MrDavid on buzztouch.com!
                    </div>
                    
                </div>
                
                <!--
                	############### Developer Notes ###############
                
                	The HTML below is used to allow a user to manipulate the advanced properties of this plugin. It's possible
                    to have a plugin that does not allow the user to change any properties using the coontrol panel.

                    ALL PLUGINS NEED TO ALLOW THE USER TO UPDATE THE SCREEN'S NICKNAME IN THEIR CONTROL PANELS.
                    
                    Each "section" below contains the HTML and form fields for some common advanced properties, along with 
                    a SAVE button. Clicking the SAVE button triggers a javascript function (included higher up in this file) 
                    that POST'S all the form field entries the user made to a .PHP file in this plugins directory. This file is
                    named save_JSON.php. It is this file that is responsible for saving the entries to the database then 
                    returning a "saved" or "error" message when it finishes. 
                    
                    Form Field Names: If you need to add additional form fields to control additional advanced properties for this
                    plugin, prepend "json_" to the name AND id of the form field. Example: If the plugin allows a user to change a title used
                    used by the plugin in the mobile app, the form field may be named: "json_title". Following this methodology allows
                    you to easily add new form fields (new advanced properties) without having to change the save_JSON.php file. This
                    save_JSON.php will automatically create the JSON data for this plugin if it finds form fields with the "json_" prefix.               
                
                -->
                
                
                
                
                
                
                
                <!-- ##################################################### -->                   
				<!-- ############### nickname property ###############-->
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_nickname');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Screen Nickname</a>
                    <div id="box_nickname" style="display:none;">
                        
                        <div style='padding-top:10px;'>
                            <b>Enter a Nickname</b><br/>
                            <input type="text" name="json_nickname" id="json_nickname" value="<?php echo fnFormOutput($nickname);?>">
                        </div>
                        
                        <div style='padding-top:5px;'>
                            <input type='button' title="save" value="save" class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_nickname');return false;">
                            <div id="saveResult_nickname" class="submit_working">&nbsp;</div>
                        </div>
            
                    </div>    
                </div>
				<!-- ############### end nickname property ############### -->
                <!-- ##################################################### -->                   
				
                
                
                
                
                
                
                
                
                
                <!-- ##################################################### -->                   
                <!-- ############### navBar properties ############### -->
				<?php
                
                    //if this screen's json has a navBarRightButtonTapLoadScreenItemId we need the name of the screen....	
                    $navBarRightButtonTapLoadScreenNickname = "";
                    $navBarRightButtonTapLoadScreenItemId = fnGetJsonProperyValue("navBarRightButtonTapLoadScreenItemId", $jsonVars);
                    if($navBarRightButtonTapLoadScreenItemId != ""){
                        $strSql = "SELECT nickname FROM " . TBL_BT_ITEMS . " WHERE guid = '" . $navBarRightButtonTapLoadScreenItemId . "' AND appGuid = '" . $appGuid . "'";
                        $navBarRightButtonTapLoadScreenNickname = fnGetOneValue($strSql, APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS);
                    }
                
                ?>
                
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_navBar');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Top Navigation Bar</a>
                    <div id="box_navBar" style="display:none;">
                    
                        <table style='margin-top:10px;'>
                            <tr>
                                <td style='vertical-align:top;'>
                    
                                    <b>Nav. Bar Title</b><br/>
                                    <input type="text" name="json_navBarTitleText" id="json_navBarTitleText" value="<?php echo fnFormOutput(fnGetJsonProperyValue("navBarTitleText", $jsonVars));?>">
                            
                                    <br/><b>Nav. Bar Background Color</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerColor.php?formElVal=json_navBarBackgroundColor" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_navBarBackgroundColor" id="json_navBarBackgroundColor" value="<?php echo fnFormOutput(fnGetJsonProperyValue("navBarBackgroundColor", $jsonVars));?>" >
                                
                                    <br/><b>Nav. Bar Style</b><br/>
                                    <select name="json_navBarStyle" id="json_navBarStyle" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>--select--</option>
                                        <option value="solid" <?php echo fnGetSelectedString("solid", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>Solid  navigation bar</option>
                                        <option value="transparent" <?php echo fnGetSelectedString("transparent", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>Transparent navigation bar</option>
                                        <option value="hidden" <?php echo fnGetSelectedString("hidden", fnGetJsonProperyValue("navBarStyle", $jsonVars));?>>Hide the navigation bar</option>
                                    </select>
                                    
                                
                                </td>
                                <td style='vertical-align:top;'>
            
                                    <b>Right Button Type</b><br/>
                                    <select name="json_navBarRightButtonType" id="json_navBarRightButtonType" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>--select--</option>
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>No right button in nav bar</option>
                                        <option value="home" <?php echo fnGetSelectedString("home", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Home</option>
                                        <option value="next" <?php echo fnGetSelectedString("next", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Next</option>
                                        <option value="infoLight" <?php echo fnGetSelectedString("infoLight", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Info Light</option>
                                        <option value="infoDark" <?php echo fnGetSelectedString("infoDark", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Info Dark</option>
                                        <option value="details" <?php echo fnGetSelectedString("details", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Details</option>
                                        <option value="done" <?php echo fnGetSelectedString("done", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Done</option>
                                        <option value="cancel" <?php echo fnGetSelectedString("cancel", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Cancel</option>
                                        <option value="save" <?php echo fnGetSelectedString("save", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Save</option>
                                        <option value="add" <?php echo fnGetSelectedString("add", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Add</option>
                                        <option value="addBlue" <?php echo fnGetSelectedString("addBlue", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Add Blue</option>
                                        <option value="compose" <?php echo fnGetSelectedString("compose", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Compose</option>
                                        <option value="reply" <?php echo fnGetSelectedString("reply", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Reply</option>
                                        <option value="action" <?php echo fnGetSelectedString("action", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Action</option>
                                        <option value="organize" <?php echo fnGetSelectedString("organize", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Organize</option>
                                        <option value="bookmark" <?php echo fnGetSelectedString("bookmark", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Bookmark</option>
                                        <option value="search" <?php echo fnGetSelectedString("search", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Search</option>
                                        <option value="refresh" <?php echo fnGetSelectedString("refresh", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Refresh</option>
                                        <option value="camera" <?php echo fnGetSelectedString("camera", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Camera</option>
                                        <option value="trash" <?php echo fnGetSelectedString("trash", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Trash</option>
                                        <option value="play" <?php echo fnGetSelectedString("play", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Play</option>
                                        <option value="pause" <?php echo fnGetSelectedString("pause", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Pause</option>
                                        <option value="stop" <?php echo fnGetSelectedString("stop", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Stop</option>
                                        <option value="rewind" <?php echo fnGetSelectedString("rewind", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Rewind</option>
                                        <option value="fastForward" <?php echo fnGetSelectedString("fastForward", fnGetJsonProperyValue("navBarRightButtonType", $jsonVars));?>>Fast Forward</option>
                                    </select>
                        
                                    <br/><b>Right Button Load Screen</b>         
                                        &nbsp;&nbsp;
                                        <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                        <a href="<?php echo $controlPanelURL;?>/bt_pickerScreen.php?appGuid=<?php echo $appGuid;?>&formElVal=json_navBarRightButtonTapLoadScreenItemId&formElLabel=json_navBarRightButtonTapLoadScreenNickname" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_navBarRightButtonTapLoadScreenNickname" id="json_navBarRightButtonTapLoadScreenNickname" value="<?php echo fnFormOutput($navBarRightButtonTapLoadScreenNickname);?>">
                                    <input type="hidden" name="json_navBarRightButtonTapLoadScreenItemId" id="json_navBarRightButtonTapLoadScreenItemId" value="<?php echo fnFormOutput(fnGetJsonProperyValue("navBarRightButtonTapLoadScreenItemId", $jsonVars));?>">
                                    
                                    <br/><b>Right Button Transition Type</b> <span class="normal">(iOS Only)</span><br/>
                                    <select name="json_navBarRightButtonTapTransitionType" id="json_navBarRightButtonTapTransitionType" style="width:250px;">
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>--select--</option>
                                        <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Default transition</option>
                                        <option value="fade" <?php echo fnGetSelectedString("fade", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Fade</option>
                                        <option value="flip" <?php echo fnGetSelectedString("flip", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Flip</option>
                                        <option value="curl" <?php echo fnGetSelectedString("curl", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Curl</option>
                                        <option value="grow" <?php echo fnGetSelectedString("grow", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Grow</option>
                                        <option value="slideUp" <?php echo fnGetSelectedString("slideUp", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Slide Up</option>
                                        <option value="slideDown" <?php echo fnGetSelectedString("slideDown", fnGetJsonProperyValue("navBarRightButtonTapTransitionType", $jsonVars));?>>Slide Down</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                                         
                        <div style="padding-top:5px;">
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_navBar');return false;">
                            <div id="saveResult_navBar" class="submit_working">&nbsp;</div>
                        </div>
                    
                    </div>
                </div>
				<!-- ############### end navBar properties ############### -->
                <!-- ##################################################### -->                   









				<!-- ############### login properties ############### -->
                <!-- ##################################################### -->                   
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_login');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Require Login</a>
                    <div id="box_login" style="display:none;">
                        
                        <div style='padding-top:10px;'>
                            <select name="json_loginRequired" id="json_loginRequired" style="width:250px;">
                                <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("logInRequired", $jsonVars));?>>--select--</option>
                                <option value="0" <?php echo fnGetSelectedString("0", fnGetJsonProperyValue("logInRequired", $jsonVars));?>>No, do not require a login</option>
                                <option value="1" <?php echo fnGetSelectedString("1", fnGetJsonProperyValue("logInRequired", $jsonVars));?>>Yes, require a login</option>
                            </select>
                       </div>
                
                        <div style='padding-top:5px;'>
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_login');return false;">
                            <div id="saveResult_login" class="submit_working">&nbsp;</div>
                        </div>
                 
                    </div>
                </div>
				<!-- ############### end login properties ############### -->
                <!-- ##################################################### -->                   








				<!-- ############### background properties ############### -->
                <!-- ##################################################### -->                   
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_backgroundColor');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Screen Background Color</a>
                    <div id="box_backgroundColor" style="display:none;">
                        
                       <table style='padding-top:15px;'>
            
                            <tr>
                                <td style='vertical-align:top;padding-left:0px;'>
                                    Enter "clear" (wihtout quotes) for a transparent background. 
                                    All other colors should be entered in hex format, include the # character like: #FFCC66.
                                </td>                
                            </tr>
                            <tr>
                                <td style='vertical-align:top;padding-left:0px;padding-top:5px;'>
                                     
                                    <b>Color</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerColor.php?formElVal=json_backgroundColor" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundColor" id="json_backgroundColor" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundColor", $jsonVars));?>">
                    
                                </td>
                            </tr>
                        </table>
            
                        <div style='padding-top:5px;padding-left:0px;'>
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_backgroundColor');return false;">
                            <div id="saveResult_backgroundColor" class="submit_working">&nbsp;</div>
                        </div>
                        
                    </div>
                 </div>
                 
                <div class='cpExpandoBox colorLightBg'>
                    <a href='#' onClick="fnExpandCollapse('box_backgroundImage');return false;"><img src='<?php echo APP_URL;?>/images/arr_right.gif' alt='arrow' />Screen Background Image</a>
                    <div id="box_backgroundImage" style="display:none;">
                            
                        <table style='padding-top:10px;'>
                            
                            <tr>
                                <td colspan='3' style='vertical-align:top;padding-left:0px;'>
                                    Image File Names or Image URL (web address)?
                                    <hr>
                                    Use an Image File Name or an Image URL - NOT BOTH.
                                    If you choose to use an Image File Name (and not a URL), you'll need to add the image to
                                    the Xcode or Eclipse project after you download the code for your app. The Image File Name value you
                                    enter in the control panel must match the file name of the image in your
                                    project. Example: mybackground.png. Do not use image file names that contain spaces or special characters.
                                    <hr>
                                    If you use a URL (and not an Image File Name), the image will be downloaded from the URL then stored on the device
                                    for offline use. The Image URL should end with the name of the image file itself. 
                                    Example: www.mysite.com/images/mybackground.png.  You'll need to figure out whether or not
                                    it's best to include them in the project or use URL's, both approaches make sense, depending on your
                                    design goals.
                                </td>                
                            </tr>
                            
                            
                            <tr>	
                                <td class='tdSort' style='padding-left:0px;font-weight:bold;padding-top:10px;'>Small Device</td>
                                <td class='tdSort' style='padding-left:25px;font-weight:bold;padding-top:10px;'>Large Device</td>
                                <td class='tdSort' style='padding-left:25px;font-weight:bold;padding-top:10px;'>Extras</td>
                            </tr>
                            <tr>
                                <td style='vertical-align:top;padding-left:0px;padding-top:15px;'>
                                
                                   <b>Image File Name</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageNameSmallDevice&fileNameOrURL=fileName&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageNameSmallDevice" id="json_backgroundImageNameSmallDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageNameSmallDevice", $jsonVars));?>">
                                    
                                    <br/><b>Image URL</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageURLSmallDevice&fileNameOrURL=URL&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageURLSmallDevice" id="json_backgroundImageURLSmallDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageURLSmallDevice", $jsonVars));?>">
                                </td>
                                <td style='vertical-align:top;padding-left:25px;padding-top:15px;'>
                                   
                                   <b>Image File Name</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageNameLargeDevice&fileNameOrURL=fileName&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageNameLargeDevice" id="json_backgroundImageNameLargeDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageNameLargeDevice", $jsonVars));?>">
                                    
                                    <br/><b>Image URL</b>
                                    &nbsp;&nbsp;
                                    <img src="<?php echo APP_URL;?>/images/arr_right.gif" alt="arrow"/>
                                    <a href="<?php echo $controlPanelURL;?>/bt_pickerFile.php?appGuid=<?php echo $appGuid;?>&formEl=json_backgroundImageURLLargeDevice&fileNameOrURL=URL&searchFolder=/images" rel="shadowbox;height=550;width=950">Select</a>
                                    <br/>
                                    <input type="text" name="json_backgroundImageURLLargeDevice" id="json_backgroundImageURLLargeDevice" value="<?php echo fnFormOutput(fnGetJsonProperyValue("backgroundImageURLLargeDevice", $jsonVars));?>">
                                 </td>
                                <td style='vertical-align:top;padding-left:25px;padding-top:15px;'>
                                    
                                    <b>Scale / Position</b><br/>
                                    <select name="json_backgroundImageScale" id="json_backgroundImageScale" style="width:150px;">
                                            <option value="" <?php echo fnGetSelectedString("", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>--select--</option>
                                            <option value="center" <?php echo fnGetSelectedString("center", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>center</option>
                                            <option value="fullScreen" <?php echo fnGetSelectedString("fullScreen", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Full Screen</option>
                                            <option value="fullScreenPreserve" <?php echo fnGetSelectedString("fullScreenPreserve", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Full Screen, Preserve Ratio</option>
                                            <option value="top" <?php echo fnGetSelectedString("top", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Top Middle</option>
                                            <option value="bottom" <?php echo fnGetSelectedString("bottom", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Bottom Middle</option>
                                            <option value="topLeft" <?php echo fnGetSelectedString("topLeft", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Top Left</option>
                                            <option value="topRight" <?php echo fnGetSelectedString("topRight", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Top Right</option>
                                            <option value="bottomLeft" <?php echo fnGetSelectedString("bottomLeft", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Bottom Left</option>
                                            <option value="bottomRight" <?php echo fnGetSelectedString("bottomRight", fnGetJsonProperyValue("backgroundImageScale", $jsonVars));?>>Bottom Right</option>
                                    </select>
                                </td>
                                  
                            </tr>
                        </table>
                            
                        <div style='padding-top:5px;padding-left:0px;'>
                            <input type='button' title="save" value="save" align='absmiddle' class="buttonSubmit" onClick="saveAdvancedProperty('saveResult_backgroundImage');return false;">
                            <div id="saveResult_backgroundImage" class="submit_working">&nbsp;</div>
                        </div>
                        
                    </div>
                  </div>

				<!-- ############### end background properties ############### -->
                <!-- ##################################################### -->                   


            </div>
            
            
            <div style='clear:both;'></div>
        	
        </div>
        
    </fieldset>
        
<?php 
	//ask the Page class to print the bottom navigation bar...
	echo $objControlPanelWebpage->fnGetBottomNavBar();
?>

</div>

<?php 
	//ask the Page class to print the closing body tag...
	echo $objControlPanelWebpage->fnGetBodyEnd(); 
?>






     