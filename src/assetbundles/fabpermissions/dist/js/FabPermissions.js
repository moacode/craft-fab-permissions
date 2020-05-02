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

	$el: null,
	data : {
		tabs: [],
		fields: []
	},
	isLoading: true,
	userGroups: {},
	loadingPromise: new $.Deferred(),
	actionFieldAndTabPermissionsUrl: 'craft-fab-permissions/fab-permissions/get-field-and-tab-permissions',
	actionUserGroupsUrl: 'craft-fab-permissions/fab-permissions/get-user-groups',
	fieldLayoutId: null,

	init: function(settings){

		this.settings = $.extend({}, Craft.FabPermissions.defaults, settings);

		// Set object properties
		this.$el = $('.fld-tabs');
		this.fieldLayoutId = $('input[name="fieldLayoutId"]').val();

		// Disable the save button until the requests have finished.
		// This prevents changes from being saved before the permissions hidden inputs have been populated.
		$('.btn.submit').addClass('disabled');

		this.isLoading = true;

		// Load the permissions adta
		this._getFabPermissions().done(function(){

		}).fail(function(){

		}).always(function(){
			this.isLoading = false;
			$('.btn.submit').removeClass('disabled');
		});
	},

	/**
	 * Loads fab permission data from the server
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @return {object} Promise
	 */
	_getFabPermissions : function(){

		var self = this;

		var $userGroupsRequest = this._makeRequest(this.actionUserGroupsUrl),
			$fieldAndTabPermissionsRequest = this._makeRequest(this.actionFieldAndTabPermissionsUrl, {fieldLayoutId: this.fieldLayoutId});

		// Create an array of deferred promises
		$.when.apply($, [$fieldAndTabPermissionsRequest, $userGroupsRequest]).done(function(){
			self.data = $fieldAndTabPermissionsRequest.responseJSON.data;
			self.userGroups = $userGroupsRequest.responseJSON.data.userGroups;
		}).always(function(){
			self.loadingPromise.resolve();
		});

		return this.loadingPromise;
	},

	/**
	 * Simple method to make an underlying POST request
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {string} url
	 * @param  {string} data
	 * @return {object}
	 */
	_makeRequest: function(url, data){
		return Craft.postActionRequest(url, data);
	},

	/**
	 * Method called when a tab is initialised
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {void}
	 */
	initTab: function($tab){

		// Set the original name on to this tab.
		// This is so we can fetch the original selections by name even after a tab is renamed.
		var fabPermissionsData = $tab.data('fabPermissions');
		$tab.data('fabPermissions', $.extend({}, fabPermissionsData, {originalName: this._getTabName($tab)}));

		// Show a loading spinner
		this.showSpinnerIcon($tab.find('.tab'));

		var self = this;
		this.loadingPromise.done(function(){
			self.populateTabInputs($tab);
		// Remove the loading class once the Fab Permissions data is loaded
		}).always(function(){

			// Get a handle on the menu data
			var $editBtn = $tab.find('.tabs .settings');
			var menuData = $editBtn.data('menubtn');
			var $menu = menuData.menu.$container;

			// Remove the disabled menu item
			$menu.find('.js--fab-set-permissions').removeClass('disabled');

			// Hide the loading spinner
			self.hideSpinnerIcon($tab);
		});
	},

	/**
	 * Method called when a field is initialised
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $field jQuery collection
	 * @return {void}
	 */
	initField: function($field){

		// Show a loading spinner
		this.showSpinnerIcon($field);

		var self = this;
		this.loadingPromise.done(function(){
			self.populateFieldInputs($field);
		// Remove the loading class once the Fab Permissions data is loaded
		}).always(function(){

			// Get a handle on the menu data
			var $editBtn = $field.find('.settings');
			var menuData = $editBtn.data('menubtn');
			var $menu = menuData.menu.$container;

			// Remove the disabled menu item
			$menu.find('.js--fab-set-permissions').removeClass('disabled');

			// Hide the loading spinner
			self.hideSpinnerIcon($field);
		});
	},

	/**
	 * Populates hidden inputs that hold the tab permission data
	 * Tab names are taken from the latest label text
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {void}
	 */
	populateTabInputs: function($tab){

		var self = this,
			hasSavedPermissions = false,
			origTabName = this._getOriginalTabName($tab),
			tabPermissions = this.data.tabs[origTabName] || null,
			hasPermissions = ($.isPlainObject(tabPermissions) && Object.keys(tabPermissions).length);

		if( hasPermissions ){
			// Show an icon on each tab bar if permissions have been set.
			this.showFabIcon($tab.find('.tab'));

			// Loop the permissions and add hidden inputs
			for(var userGroupHandle in tabPermissions){
				for(var type in tabPermissions[userGroupHandle]){
					var hasPermission = tabPermissions[userGroupHandle][type];
					self.addTabInput($tab, type, userGroupHandle, hasPermission);
				}
			}
		}

		// Reset the form unload values, as we've injected hidden inputs
		self.resetFormUnload();
	},

	/**
	 * Populates hidden inputs that hold the field permission data
	 * Tab names are taken from the latest label text
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $field jQuery collection
	 * @return {void}
	 */
	populateFieldInputs: function($field){

		var self = this,
			hasSavedPermissions = false,
			fieldPermissions = this.data.fields[$field.data('id')] || null,
			hasPermissions = ($.isPlainObject(fieldPermissions) && Object.keys(fieldPermissions).length);

		if( hasPermissions ){
			// Show an icon on each field bar if permissions have been set.
			this.showFabIcon($field);

			// Loop the permissions and add hidden inputs
			for(var userGroupHandle in fieldPermissions){
				for(var type in fieldPermissions[userGroupHandle]){
					var hasPermission = fieldPermissions[userGroupHandle][type];
					self.addFieldInput($field, type, userGroupHandle, hasPermission);
				}
			}
		}

		// Reset the form unload values, as we've injected hidden inputs
		self.resetFormUnload();
	},

	/**
	 * Resets the layout form special forms unload
	 * @author Josh Smith <josh@batch.nz>
	 * @return void
	 */
	resetFormUnload(){
		$('#content').find('form').data('initialSerializedValue', false);
		Craft.cp.initSpecialForms();
	},

	/**
	 * Shows a tab spinner icon
	 */
	showSpinnerIcon: function($el){
		var $spinner = $el.find('.js--fab-spinner');

		if( $spinner.length ){
			$spinner.show();
		} else {
			$el.children().first().after('<div class="fab-inline fab-spinner spinner icon js--fab-spinner"/>');
		}
	},

	/**
	 * Hides a tab spinner icon
	 */
	hideSpinnerIcon: function($el){
		var $spinner = $el.find('.js--fab-spinner');
		if( $spinner.length ){
			$spinner.hide();
		}
	},

	/**
	 * Shows a tab fab permissions icon
	 */
	showFabIcon: function($el){
		var $fabIcon = $el.find('.js--fab-users');

		if( $fabIcon.length ){
			$fabIcon.show();
		} else {
			$el.find('.js--fab-spinner').after('<div class="fab-inline icon users js--fab-users"/>');
		}
	},

	/**
	 * Hides a tab fab permissions icon
	 */
	hideFabIcon: function($el){
		var $fabIcon = $el.find('.js--fab-users').eq(0);
		if( $fabIcon.length ){
			$fabIcon.hide();
		}
	},

	/**
	 * Adds a tab hidden input to the DOM
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object}  $tab          jQuery collection
	 * * @param  {string}  type        Permission type, either canView or canEdit
	 * @param  {string}  handle        User group handle
	 * @param  {Boolean} hasPermission Whether or not this user group has permissions to view this tab
	 */
	addTabInput: function($tab, type, handle, hasPermission){
		$tab.append('<input class="fab-id-input js--fab-tab-input" type="hidden" name="'+this._getFieldInputName($tab)+'['+handle+']['+type+']" value="'+(hasPermission ? '1' : '0')+'">');
	},

	/**
	 * Removes all fab permissions hidden inputs for the given tab
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {void}
	 */
	removeTabInputs: function($tab){
		$tab.find('.js--fab-tab-input').remove();
	},

	/**
	 * Adds a field hidden input to the DOM
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object}  $field        jQuery collection
	 * @param  {string}  type          Permission type, either canView or canEdit
	 * @param  {string}  handle        User group handle
	 * @param  {Boolean} hasPermission Whether or not this user group has permissions to view this field
	 */
	addFieldInput: function($field, type, handle, hasPermission){
		$field.append('<input class="fab-id-input js--fab-field-input" type="hidden" data-id="'+$field.data('id')+'" data-handle="'+handle+'" data-type="'+type+'" name="fieldPermissions['+$field.data('id')+']['+handle+']['+type+']" value="'+(hasPermission ? '1' : '0')+'">');
	},

	/**
	 * Removes all fab permissions hidden inputs for the given field
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $field jQuery collection
	 * @return {void}
	 */
	removeFieldInputs: function($field){
		$field.find('.js--fab-field-input').remove();
	},

	/**
	 * Returns the fab permissions hidden field input name
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {string}
	 */
	_getFieldInputName: function($tab){
		return Craft.FieldLayoutDesigner.prototype.getFieldInputName.call(this, this._getTabName($tab));
	},

	/**
	 * Returns the current tab name
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {string}      Tab name
	 */
	_getTabName: function($tab){
		var $labelSpan = $tab.find('.tabs .tab span');
		return $labelSpan.text();
	},

	/**
	 * Returns the original tab name, from the tab's data
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {string}      Original tab name
	 */
	_getOriginalTabName: function($tab){
		return $tab.data('fabPermissions').originalName || '';
	},

	/**
	 * Returns whether a particular permission is set or not
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {string}  type 	Type of permission to check
	 * @param  {string}  handle User group handle
	 * @return {Boolean}
	 */
	isPermissionSet: function($tab, type, handle){
		var $permissions = $tab.find('.js--fab-tab-input');
		if( ! $permissions.length ) return true;

		var self = this,
			matchedHandle = false;

		// Loop each permission input and return whether a permission is set
		$permissions.each(function(i, input){

			var $input = $(input),
				regexp = new RegExp(self.escapeRegExp(self._getFieldInputName($tab)+'['+handle+']['+type+']')),
				matches = $input.attr('name').match(regexp);

			// Mark this checkbox as checked if it matches the handle and it's marked as selected
			if( matches && $input.val() === '1' ) matchedHandle = true;
		});

		return matchedHandle;
	},

	/**
	 * Returns whether a particular permission is set or not
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {string}  type 	Type of permission to check
	 * @param  {string}  handle User group handle
	 * @return {Boolean}
	 */
	isFieldPermissionSet: function($field, type, handle){
		var $permissions = $field.find('.js--fab-field-input');
		if( ! $permissions.length ) return true;

		var self = this,
			matchedHandle = false;

		// Loop each permission input and return whether a permission is set
		$permissions.each(function(i, input){

			var $input = $(input);

			if(
				$field.data('id') === $input.data('id') &&
				$input.data('type') === type &&
				$input.data('handle') === handle &&
				$input.val() === '1'
			) matchedHandle = true;
		});

		return matchedHandle;
	},

	/**
	 * Helper function to escape regular expressions
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {string} string
	 * @return {string}
	 */
	escapeRegExp : function(string) {
		return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
	}
},
{
	defaults: {
		contentSummary: [],
		fieldInputName: 'tabPermissions[__TAB_NAME__]', // Awkwardly named, but kept to utilize the default Craft methods that use this property.
		onSubmit: $.noop,
		redirect: null
	}
});

/**
 * Initialises FabPermissions
 * @see  Craft.FieldLayoutDesigner.prototype.init
 * @return {void}
 */
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
	var $menuOption = $('<li><a data-action="setpermissions" class="disabled js--fab-set-permissions">' + Craft.t('app', 'Set Permissions') + '</a></li>');

	// Add it to the menu collection, and register the option within the Garnish Menu object
	$menu.find('ul').prepend($menuOption);
	menuData.menu.addOptions($menuOption.find('a'));

	FabPermissions.initTab($tab);
};

/**
 * Method that handles tab option selections
 * @see  Craft.FieldLayoutDesigner.prototype.onTabOptionSelect
 * @param  {object} option Option DOM fragment
 * @return {void}
 */
var onTabOptionSelect = Craft.FieldLayoutDesigner.prototype.onTabOptionSelect;
Craft.FieldLayoutDesigner.prototype.onTabOptionSelect = function(option) {

	// Call the original method
	onTabOptionSelect.call(this, option);

	if (!this.settings.customizableTabs) return;

	var $option = $(option),
		$tab = $option.data('menu').$anchor.parent().parent().parent(),
		action = $option.data('action');

	if( $option.hasClass('disabled') ) return;

	switch (action) {
		case 'setpermissions': {
			this.setPermissionsTab($tab);
			break;
		}
	}
};

/**
 * Update the tab inputs when a tab is renamed
 * @see  Craft.FieldLayoutDesigner.prototype.renameTab
 * @param  {object} $tab jQuery collection
 * @return {void}
 */
var renameTab = Craft.FieldLayoutDesigner.prototype.renameTab;
Craft.FieldLayoutDesigner.prototype.renameTab = function($tab) {
	renameTab.call(this, $tab);

	// Remove tab inputs, and re-populate. This automatically re-generates inputs with the new tab name.
	FabPermissions.removeTabInputs($tab);
	FabPermissions.populateTabInputs($tab);
};

/**
 * Opens a modal to set user permissions on the selected tab.
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 * @param  {object} $tab jQuery collection
 */
Craft.FieldLayoutDesigner.prototype.setPermissionsTab = function($tab){
	new Craft.TabUserPermissionSelectorModal({$el: $tab, type: 'tab'});
};

/**
 * Extend the field layout designer with options to set user permissions on fields and tabs
 * @type {function}
 */
var initField = Craft.FieldLayoutDesigner.prototype.initField;
Craft.FieldLayoutDesigner.prototype.initField = function($field) {
	initField.call(this, $field);

	// Extract the edit button settings from the tab
	var $editBtn = $field.find('.settings');
	var menuData = $editBtn.data('menubtn');
	var $menu = menuData.menu.$container;

	// Create a new menu option element
	var $menuOption = $('<li><a data-action="setpermissions" class="disabled js--fab-set-permissions">' + Craft.t('app', 'Set Permissions') + '</a></li>');

	// Add it to the menu collection, and register the option within the Garnish Menu object
	$menu.find('ul').prepend($menuOption);
	menuData.menu.addOptions($menuOption.find('a'));

	FabPermissions.initField($field);
};

/**
 * Method that handles field option selections
 * @see  Craft.FieldLayoutDesigner.prototype.onFieldOptionSelect
 * @param  {object} option Option DOM fragment
 * @return {void}
 */
var onFieldOptionSelect = Craft.FieldLayoutDesigner.prototype.onFieldOptionSelect;
Craft.FieldLayoutDesigner.prototype.onFieldOptionSelect = function(option) {

	// Call the original method
	onFieldOptionSelect.call(this, option);

	var $option = $(option),
		$field = $option.data('menu').$anchor.parent(),
		action = $option.data('action');

	switch (action) {
		case 'setpermissions': {
			this.setPermissionsField($field);
			break;
		}
	}
};

/**
 * Opens a modal to set user permissions on the selected field.
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 * @param  {object} $field jQuery collection
 */
Craft.FieldLayoutDesigner.prototype.setPermissionsField = function($field){
	new Craft.FieldUserPermissionSelectorModal({$el: $field, type: 'field'});
};

/**
 * Base Modal object that allows a user to set permissions on a tab or field
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 */
Craft.BaseUserPermissionSelectorModal = Garnish.Modal.extend({

	settings: {},
	closeOtherModals: true,
	shadeClass: 'modal-shade dark',
	userGroups: [],
	$form : $(),
	$submitBtn: $(),
	actionUrl : 'craft-fab-permissions/fab-permissions/get-user-groups',

	init: function(settings) {

		var self = this;
		this.settings = $.extend({}, Craft.BaseUserPermissionSelectorModal.defaults, settings);

		this.$form = $(
			'<form class="modal elementselectormodal" method="post" accept-charset="UTF-8">' +
			Craft.getCsrfInput() +
			'</form>'
		).appendTo(Garnish.$bod);

		var $body = $(
			'<div class="body" style="overflow-y: scroll;">' +
				'<div class="content-summary">' +
					'<p style="font-size: 20px;font-weight: bold;">'+this.titleFormat(this.settings.type)+' Permissions</p>' +
					'<p style="padding: 0rem 0 2rem 0;">Choose which user groups have access to this '+this.settings.type+'.</p>' +
				'</div>' +
				'<div class="tableview">' +
					'<table class="data fullwidth js--fab-table">' +
						'<thead>' +
							'<tr>' +
							'</tr>' +
						'</thead>' +
						'<tbody>' +
						'</tbody>' +
					'</table>' +
				'</div>' +
			'</div>'
		).appendTo(this.$form),

		$tableHeadings = this.getTableHeadings().appendTo($body.find('thead tr')),

		$footer = $('<div class="footer"/>').appendTo(this.$form),
		$secondaryButtons = $('<div class="left secondary-buttons fab-my-0"></div>').appendTo($footer),
		$buttons = $('<div class="buttons right fab-my-0"/>').appendTo($footer),
		$cancelBtn = $('<div class="btn">' + Craft.t('app', 'Cancel') + '</div>').appendTo($buttons),
		$clearBtn = $('<div data-icon-after="trash" class="btn submit">'+ Craft.t('app', 'Clear') +'</div>').appendTo($secondaryButtons);

		// Define the submit button
		this.$submitBtn = $('<input type="submit" class="btn submit" value="' + Craft.t('app', 'Save') + '" />').appendTo($buttons);

		// Add event listeners
		this.addListener($clearBtn, 'click', 'clearPermissions');
		this.addListener($cancelBtn, 'click', 'hide');
		this.addListener(this.$form, 'submit', 'handleSubmit');

		// Fetch and populate the user group information
		this._populateUserGroups(FabPermissions.userGroups);

		this.base(this.$form, settings);
	},

	titleFormat: function(text){
		if( typeof text !== 'string' ) return '';
		return text[0].toUpperCase()+text.slice(1);
	},

	getTableHeadings: function(){
		return $(
			'<th scope="col" style="min-width: 50%;">User Group(s)</th>' +
			'<th scope="col">Can View</th>' +
			'<th scope="col">Can Edit</th>'
		);
	},

	handleSubmit: function(e){
		throw Error('handleSubmit must not be called directly.');
	},

	/**
	 * Populates the user group checkboxes into the modal
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {array} userGroups  An array of user groups
	 * @return {void}
	 */
	_populateUserGroups: function(userGroups){

		var self = this,
			$table = this.$form.find('.js--fab-table'),
			$tableBody = $table.find('tbody');

		// Set a default admin permissions row.
		$tableBody.append(this._createTableRow({
			name: 'Admin',
			handle: 'admin',
			canView: {
				checked: true,
				disabled: true
			},
			canEdit: {
				checked: true,
				disabled: true
			}
		}));

		// Loop the groups and populate checkboxes
		userGroups.forEach(function(userGroup){

			// Use saved defaults if the user hasn't made changes yet.
			var canView = self._isPermissionSet(self.settings.$el, 'canView', userGroup.handle);
			var canEdit = self._isPermissionSet(self.settings.$el, 'canEdit', userGroup.handle);

			// Create a table row and assign click event handlers
			var $tableRow = self._createTableRow({
				name: userGroup.name,
				handle: userGroup.handle,
				canView: {
					checked: canView,
					disabled: false
				},
				canEdit: {
					checked: canEdit,
					disabled: !canView
				}
			}).on('change', 'input', function(e){
				self._handleOnPermissionClick($(this), !$(this).prop('checked'));
			}).on('click', 'td', function(e){
				var $checkbox = $(this).find('input[type="checkbox"]');
				self._handleOnPermissionClick($checkbox, !$checkbox.prop('checked'));
			});

			$tableBody.append($tableRow);
		});
	},
	_handleOnPermissionClick: function($checkbox, value) {
		if( $checkbox.length === 0 ) return;
		if( $checkbox.prop('disabled') ) return;

		// Set the checkbox value
		$checkbox.prop('checked', value);

		// Disable the edit permission if we can't view
		if( $checkbox.data('type') === 'canView' ){
			var $editCheckbox = $checkbox.parents('tr').find('[data-type="canEdit"]');
			if( $editCheckbox.length === 0 ) return;

			$editCheckbox.prop('disabled', !value)
			if( !value ) $editCheckbox.prop('checked', false);
		}
	},
	_createTableRow: function(rowData){
		return $(
			'<tr>' +
				'<td>' +
					'<div class="element small">' +
						'<div class="label">' +
							'<span class="title">'+Craft.t('app', rowData.name)+'</span>' +
						'</div>' +
					'</div>' +
				'</td>' +
				'<td>' +
					'<input type="checkbox" data-type="canView" '+(rowData.canView.checked ? 'checked="checked"' : '')+' '+(rowData.canView.disabled ? 'disabled="disabled"' : '')+' name="'+rowData.handle+'" value="'+rowData.handle+'"/> ' +
				'</td>' +
				'<td>' +
					'<input type="checkbox" data-type="canEdit" '+(rowData.canEdit.checked ? 'checked="checked"' : '')+' '+(rowData.canEdit.disabled ? 'disabled="disabled"' : '')+' name="'+rowData.handle+'" value="'+rowData.handle+'"/> ' +
				'</td>' +
			'</tr>'
		);
	},

	_isPermissionSet: function($el, type, handle){
		return FabPermissions.isPermissionSet($el, type, handle);
	}
});

/**
 * Extends the base user permissions modal specific for tabs
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 */
Craft.TabUserPermissionSelectorModal = Craft.BaseUserPermissionSelectorModal.extend({

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
		FabPermissions.removeTabInputs(this.settings.$el);

		var self = this;

		// Loop through each non-disabled checkbox option, and render hidden inputs into the DOM.
		this.$form.find('.tableview input[type="checkbox"]').each(function(i, checkbox){

			var $checkbox = $(checkbox);

			// Append the hidden input
			FabPermissions.addTabInput(self.settings.$el, $checkbox.data('type'), $checkbox.val(), $checkbox.is(':checked'));
		});

		FabPermissions.showFabIcon(self.settings.$el.find('.tab'));

		return this.hide();
	},

	/**
	 * Clears selected permissions
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} e Event object
	 * @return {void}
	 */
	clearPermissions: function(e){
		FabPermissions.removeTabInputs(this.settings.$el);
		FabPermissions.hideFabIcon(this.settings.$el);
		this.hide();
	},

	getTableHeadings: function(){
		return $(
			'<th scope="col">User Group(s)</th>' +
			'<th scope="col">Can View</th>'
		);
	},

	_createTableRow: function(rowData){
		return $(
			'<tr>' +
				'<td>' +
					'<div class="element small">' +
						'<div class="label">' +
							'<span class="title">'+Craft.t('app', rowData.name)+'</span>' +
						'</div>' +
					'</div>' +
				'</td>' +
				'<td>' +
					'<input type="checkbox" data-type="canView" '+(rowData.canView.checked ? 'checked="checked"' : '')+' '+(rowData.canView.disabled ? 'disabled="disabled"' : '')+' name="'+rowData.handle+'" value="'+rowData.handle+'"/> ' +
				'</td>' +
			'</tr>'
		);
	},
});

/**
 * Extends the base user permissions modal specific for fields
 * @author Josh Smith <josh.smith@platocreative.co.nz>
 */
Craft.FieldUserPermissionSelectorModal = Craft.BaseUserPermissionSelectorModal.extend({

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
		FabPermissions.removeFieldInputs(this.settings.$el);

		var self = this;

		// Loop through each non-disabled checkbox option, and render hidden inputs into the DOM.
		this.$form.find('.tableview input[type="checkbox"]').each(function(i, checkbox){

			var $checkbox = $(checkbox);

			// Append the hidden input
			FabPermissions.addFieldInput(self.settings.$el, $checkbox.data('type'), $checkbox.val(), $checkbox.is(':checked'));
		});

		FabPermissions.showFabIcon(self.settings.$el);

		return this.hide();
	},

	/**
	 * Clears selected permissions
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} e Event object
	 * @return {void}
	 */
	clearPermissions: function(e){
		FabPermissions.removeFieldInputs(this.settings.$el);
		FabPermissions.hideFabIcon(this.settings.$el);
		this.hide();
	},

	/**
	 * Returns whether a field permission is set
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object}  $el    jQuery collection
	 * @param  {string}  type 	Type of permission
	 * @param  {string}  handle User group handle
	 * @return {Boolean}
	 */
	_isPermissionSet: function($el, type, handle){
		return FabPermissions.isFieldPermissionSet($el, type, handle);
	}
});
