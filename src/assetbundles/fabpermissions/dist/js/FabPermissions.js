/**
 * FabPermissions plugin for Craft CMS
 *
 * FabPermissions JS
 *
 * @author    Josh Smith
 * @copyright Copyright (c) 2019 Josh Smith
 * @link      https://joshsmith.dev
 * @package   FabPermissions
 * @since     1.0.0
 */

/**
 * Define a global to hold the FabPermissions instance
 */
var FabPermissions;

/**
 * Object that handles the getting and setting of permissions
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 */
Craft.FabPermissions = Garnish.Base.extend({

	$tabs: null,
	$fields: null,
	data: null,
	actionUrl: 'craft-fab-permissions/fab-permissions/get-user-groups',

	init: function(settings){
		this.settings = $.extend({}, Craft.FabPermissions.defaults, settings);
	},

	initTab: function($tab){
		// Set the original name on to this tab.
		// This is so we can fetch the original selections by name even after a tab is renamed.
		var $labelSpan = $tab.find('.tabs .tab span');
		var fabPermissionsData = $tab.data('fabPermissions');
		$tab.data('fabPermissions', $.extend({}, fabPermissionsData, {originalName: $labelSpan.text()}));

		var self = this;
		this._getTabPermissions($tab).done(function(){
			self.populateTabInputs($tab);
		});
	},
	populateTabInputs: function($tab){
		var userGroups = $tab.data('fabPermissions').userGroups;

		var self = this,
			hasSavedPermissions = false;

		// Loop the user groups and populate the default inputs
		userGroups.forEach(function(group){
			var hasPermission = group.permission === 1 || group.permission === null;
			self.addTabInput($tab, group.handle, hasPermission);
			if( group.permission != null ) hasSavedPermissions = true;
		});

		// Show an icon on each tab bar if permissions have been set.
		if( hasSavedPermissions ){
			this.showTabIcon($tab);
		}

		// Reset the form unload values, as we've injected hidden inputs
		Craft.cp.initConfirmUnloadForms();
	},
	showTabIcon: function($tab){
		var $tabIcon = $tab.find('.js--fab-users');

		if( $tabIcon.length ){
			$tabIcon.show();
		} else {
			$tab.find('.tab').append('<span class="icon users js--fab-users"/>');
		}
	},
	hideTabIcon: function($tab){
		var $tabIcon = $tab.find('.js--fab-users');
		if( $tabIcon.length ){
			$tabIcon.hide();
		}
	},
	addTabInput: function($tab, handle, hasPermission){
		$tab.append('<input class="fab-tab-id-input" type="hidden" name="' + this._getFieldInputName($tab) + '[' + handle + ']" value="'+(hasPermission ? '1' : '0')+'">')
	},
	removeTabInputs: function($tab){
		$tab.find('.fab-tab-id-input').remove();
	},
	addField: function(){

	},
	removeField: function(){

	},
	_getFieldInputName: function($tab){
		var $labelSpan = $tab.find('.tabs .tab span'),
            name = $labelSpan.text();

        return Craft.FieldLayoutDesigner.prototype.getFieldInputName.call(this, name);
	},
	_getTabPermissions: function($tab){
		var self = this;
		return Craft.postActionRequest(this.actionUrl, {
			fieldLayoutId: $('input[name="fieldLayoutId"]').val(),
			tabName: $tab.data('fabPermissions').originalName
		}).done(function(response){
			var tabData = $tab.data('fabPermissions');
			$tab.data('fabPermissions', $.extend(tabData, {userGroups: response.data.userGroups}));
		});
	},

	/**
	 * Returns whether a particular permission is set or not
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {string}  handle User group handle
	 * @return {Boolean}
	 */
	isPermissionSet: function($tab, handle){
		var $permissions = $tab.find('.fab-tab-id-input');
		if( ! $permissions.length ) return false;

		var self = this,
			matchedHandle = false;

		// Loop each permission input and return whether a permission is set
		$permissions.each(function(i, input){

			var $input = $(input),
				regexp = new RegExp(self.escapeRegExp(self._getFieldInputName($tab)+'['+handle+']')),
				matches = $input.attr('name').match(regexp);

			// Mark this checkbox as checked if it matches the handle and it's marked as selected
			if( matches && $input.val() === '1' ) matchedHandle = true;
		});

		return matchedHandle;
	},

	escapeRegExp : function(string) {
		return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
	}
},
{
    defaults: {
        contentSummary: [],
        fieldInputName: 'tabPermissions[__TAB_NAME__]',
        onSubmit: $.noop,
        redirect: null
    }
});

var init = Craft.FieldLayoutDesigner.prototype.init;
Craft.FieldLayoutDesigner.prototype.init = function(container, settings) {

	FabPermissions = new Craft.FabPermissions();
	init.apply(this, arguments);

};

/**
 * Extend the field layout designer with options to set user permissions on fields and tabs
 * @type {function}
 */
var initTab = Craft.FieldLayoutDesigner.prototype.initTab;
Craft.FieldLayoutDesigner.prototype.initTab = function($tab) {
	initTab.call(this, $tab);

	if (!this.settings.customizableTabs) return;

	// Extract the edit button settings from the tab
	var $editBtn = $tab.find('.tabs .settings');
	var menuData = $editBtn.data('menubtn');
	var $menu = menuData.menu.$container;

	// Create a new menu option element
	var $menuOption = $('<li><a data-action="setpermissions">' + Craft.t('app', 'Set Permissions') + '</a></li>');

	// Add it to the menu collection, and register the option within the Garnish Menu object
	$menu.find('ul').prepend($menuOption);
	menuData.menu.addOptions($menuOption.find('a'));

	FabPermissions.initTab($tab);
};

var onTabOptionSelect = Craft.FieldLayoutDesigner.prototype.onTabOptionSelect;
Craft.FieldLayoutDesigner.prototype.onTabOptionSelect = function(option) {

	// Call the original method
	onTabOptionSelect.call(this, option);

    if (!this.settings.customizableTabs) return;

    var $option = $(option),
        $tab = $option.data('menu').$anchor.parent().parent().parent(),
        action = $option.data('action');

    switch (action) {
        case 'setpermissions': {
            this.setPermissionsTab($tab);
            break;
        }
    }
};

/**
 * Opens a modal to set user permissions on the selected tab.
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 * @param  {object} $tab jQuery collection
 */
Craft.FieldLayoutDesigner.prototype.setPermissionsTab = function($tab){
	new Craft.UserPermissionSelectorModal({$tab: $tab});
};

/**
 * Modal object that allows a user to set permissions on a tab or field
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 */
Craft.UserPermissionSelectorModal = Garnish.Modal.extend({

	settings: {},
	userGroups: [],
	$form : $(),
	$submitBtn: $(),
	actionUrl : 'craft-fab-permissions/fab-permissions/get-user-groups',

	init: function(settings) {

		var self = this;
		this.settings = $.extend({}, Craft.UserPermissionSelectorModal.defaults, settings);

		this.$form = $(
	        '<form class="modal fitted deleteusermodal" method="post" accept-charset="UTF-8">' +
	        Craft.getCsrfInput() +
	        '<input type="hidden" name="action" value="users/delete-user"/>' +
	        (!Garnish.isArray(this.userId) ? '<input type="hidden" name="userId" value="' + this.userId + '"/>' : '') +
	        (settings.redirect ? '<input type="hidden" name="redirect" value="' + settings.redirect + '"/>' : '') +
	        '</form>'
	    ).appendTo(Garnish.$bod);

	    var $body = $(
	        '<div class="body">' +
	        	'<div class="content-summary">' +
	        		'<p>' + Craft.t('app', 'Choose which user groups have access to this tab') + '</p>' +
	        	'</div>' +
	        	'<div class="options">' +
	        		'<div class="spinner"/>' +
	        	'</div>' +
	        '</div>'
	    ).appendTo(this.$form),

	    $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
	    $buttons = $('<div class="buttons right"/>').appendTo($footer),
	    $cancelBtn = $('<div class="btn">' + Craft.t('app', 'Cancel') + '</div>').appendTo($buttons);

	    // Define the submit button
	    this.$submitBtn = $('<input type="submit" class="btn submit" value="' + Craft.t('app', 'Save') + '" />').appendTo($buttons);

	    // Add event listeners
	    this.addListener($cancelBtn, 'click', 'hide');
		this.addListener(this.$form, 'submit', 'handleSubmit');

	    // Fetch and populate the user group information
	    this._populateUserGroups(this.settings.$tab.data('fabPermissions').userGroups);
	 //    this._getUserGroups().done(function(response){
		// 	self._populateUserGroups(response.data.userGroups);
		// });

	    this.base(this.$form, settings);
	},

	/**
	 * Handles the form submission.
	 * This populates hidden inputs with the selected user group handles.
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} e Event object
	 * @return {void}
	 */
	handleSubmit: function(e){
		e.preventDefault();

		// Remove existing selections
		FabPermissions.removeTabInputs(this.settings.$tab);

		var self = this;
			// selectionData = {};

		// selectionData[self._getFieldInputName()] = [];

		// Loop through each non-disabled checkbox option, and render hidden inputs into the DOM.
		this.$form.find('.options input[type="checkbox"]').each(function(i, checkbox){

			var $checkbox = $(checkbox);
			if( !$checkbox.is(':disabled') ){

				// Set properties on the element data
				// var checkboxOption = {};
				// checkboxOption[$checkbox.val()] = $checkbox.is(':checked');
				// selectionData[self._getFieldInputName()].push(checkboxOption);

				// Append the hidden input
				FabPermissions.addTabInput(self.settings.$tab, $checkbox.val(), $checkbox.is(':checked'));
				// self.settings.$tab.append('<input class="fab-tab-id-input" type="hidden" name="' + self._getFieldInputName() + '[' + $checkbox.val() + ']" value="'+($checkbox.is(':checked') ? '1' : '0')+'">')
			}
		});

		FabPermissions.showTabIcon(self.settings.$tab);

		// // Set the selections into data
		// var data = this.settings.$tab.data('fabPermissions');
		// data.isEdited = true;
		// data.selectionData = $.extend({}, data.selectionData, selectionData);
		// this.settings.$tab.data('fabPermissions', data);

		return this.hide();
	},

	// /**
	//  * Calls the field layout designer object, using a context of "this".
	//  * @author Josh Smith <josh.smith@platocreative.co.nz>
	//  * @return {string} field name based off of the default set on this object
	//  */
	// _getFieldInputName: function(){
	// 	var $labelSpan = this.settings.$tab.find('.tabs .tab span'),
 //            name = $labelSpan.text();

 //        return Craft.FieldLayoutDesigner.prototype.getFieldInputName.call(this, name);
	// },

	// /**
	//  * Fires off an action request to retrieve user group details
	//  * @author Josh Smith <josh.smith@platocreative.co.nz>
	//  * @return {object}
	//  */
	// _getUserGroups: function(){
	// 	var self = this;
	// 	return Craft.postActionRequest(this.actionUrl, {
	// 		fieldLayoutId: $('input[name="fieldLayoutId"]').val(),
	// 		tabName: this.settings.$tab.data('fabPermissions').originalName
	// 	}).done(function(response){
	// 		var tabData = self.settings.$tab.data('fabPermissions');
	// 		self.settings.$tab.data('fabPermissions', $.extend(tabData, {userGroups: response.data.userGroups}));
	// 	});
	// },

	/**
	 * Populates the user group checkboxes into the modal
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {array} userGroups  An array of user groups
	 * @return {void}
	 */
	_populateUserGroups: function(userGroups){

		var self = this,
			$options = this.$form.find('.options');

		// Set a default admin permissions checkbox.
		$options.html('<label class="disabled"><input type="checkbox" checked disabled/> ' + Craft.t('app', 'Admin') + '</label>');

		// Loop the groups and populate checkboxes
		userGroups.forEach(function(userGroup){

			// Use saved defaults if the user hasn't made changes yet.
			// var isPermissionSet = self._hasBeenEdited()
			// 	? self._isPermissionSet(userGroup.handle)
			// 	: userGroup.permission;
			var isPermissionSet = FabPermissions.isPermissionSet(self.settings.$tab, userGroup.handle);

			$options.append('<div><label><input type="checkbox" '+(isPermissionSet ? 'checked="checked"' : '')+' value="'+userGroup.handle+'" name="'+userGroup.handle+'"/> ' + Craft.t('app', userGroup.name) + '</label></div>');
		});
	},

	// *
	//  * Returns whether the permissions for this tab have been edited or not
	//  * @author Josh Smith <josh.smith@platocreative.co.nz>
	//  * @return {Boolean}

	// _hasBeenEdited: function(){
	// 	return this.settings.$tab.data('fabPermissions').isEdited || false;
	// },
},
// {
//     defaults: {
//         contentSummary: [],
//         fieldInputName: 'tabPermissions[__TAB_NAME__]',
//         onSubmit: $.noop,
//         redirect: null
//     }
// }
);
