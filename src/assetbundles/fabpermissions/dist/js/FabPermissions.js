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
var FieldLayoutDesigner;

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

	init(settings){

		var self = this;
		this.settings = $.extend({}, Craft.FabPermissions.defaults, settings);

		// Set object properties
		this.$el = $('.fld-tabs');
		this.fieldLayoutId = $('input[name="fieldLayoutId"]').val();

		// Disable the save button until the requests have finished.
		// This prevents changes from being saved before the permissions hidden inputs have been populated.
		$('.btn.submit').addClass('disabled');

		this.isLoading = true;

		// Send information to the server that the loading of field and tab data never completed
		Craft.cp.on('beforeSaveShortcut', $.proxy(function() {
			if( self.isLoading === true ){
				self.$el.append('<input type="hidden" name="fabPermissionsAbort" value="1">');
			}
		}));

		// Load the permissions data
		this._getFabPermissions().fail(function(){
			console.error('Failed to load Field and Tab permissions.');
		}).always(function(){
			self.isLoading = false;
			$('.btn.submit').removeClass('disabled');
		});
	},

	/**
	 * Loads fab permission data from the server
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @return {object} Promise
	 */
	_getFabPermissions (){

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
	_makeRequest(url, data){
		return Craft.postActionRequest(url, data);
	},

	/**
	 * Method called when a tab is initialised
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {void}
	 */
	initTab($tab){

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
			self.hideSpinnerIcon($tab.find('.tab'));

			// Allow users to edit tab permissions by clicking the icon
			$tab.find('.js--fab-icon').eq(0).on('click', function(e){
				e.preventDefault();
				FieldLayoutDesigner.setPermissionsTab($tab);
			});
		});
	},

	/**
	 * Method called when a field is initialised
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $field jQuery collection
	 * @return {void}
	 */
	initElement($field){
		var self = this;
		var fldElement = $field.data().fldElement;

		// Don't process title fields
		if( fldElement.config.type === "craft\\fieldlayoutelements\\EntryTitleField" ){
			return;
		}

		// Show a loading spinner
		this.showSpinnerIcon($field);

		// Populate the field permissions input data
		this.loadingPromise.done(function(){
			self.populateFieldInputs($field);
		// Remove the loading class once the Fab Permissions data is loaded
		}).always(function(){
			// Hide the loading spinner
			self.hideSpinnerIcon($field);

			// Allow users to edit field permissions by clicking the icon
			$field.find('.js--fab-icon').on('click', function(e){
				e.preventDefault();
				FieldLayoutDesigner.setPermissionsField($field);
			});
		});

		// Extend the field HUD
		fldElement.on('createSettingsHud', function(){
			// Create the "Set Permissions" button and inject into the HUD footer
			var $btn = $(`
				<button class="btn disabled">
					${Craft.t('app', 'Set Permissions')}
				</button>
			`).prependTo(fldElement.hud.$footer.find('.buttons'));

			// Remove the disabled menu item if the loading request has finished
			self.loadingPromise.done(function(){
				$btn.removeClass('disabled');
			});

			// Add an event handler to open the permissions modal when clicked
			$btn.on('click', function(e){
				e.preventDefault();
				if( $btn.is('.disabled') ) return;

				// Hide the HUD and open the permission field modal
				fldElement.hud.hide();
				FieldLayoutDesigner.setPermissionsField($field);
			});
		});
	},

	/**
	 * Populates hidden inputs that hold the tab permission data
	 * Tab names are taken from the latest label text
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {void}
	 */
	populateTabInputs($tab){

		var self = this,
			hasSavedPermissions = false,
			origTabName = this._getOriginalTabName($tab),
			tabPermissions = this.data.tabs[origTabName] || null,
			hasPermissions = ($.isPlainObject(tabPermissions) && Object.keys(tabPermissions).length);

		if( hasPermissions ){
			// Show an icon on each tab bar if permissions have been set.
			this.setHasPermissionsIcon($tab.find('.tab'));

			// Loop the permissions and add hidden inputs
			for(var userGroupHandle in tabPermissions){
				for(var type in tabPermissions[userGroupHandle]){
					var hasPermission = tabPermissions[userGroupHandle][type];
					self.addTabInput($tab, type, userGroupHandle, hasPermission);
				}
			}
		} else {
			this.setHasNoPermissionsIcon($tab.find('.tab'));
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
	populateFieldInputs($field) {

		var self = this,
			hasSavedPermissions = false,
			fldElement = $field.data().fldElement,
			fieldPermissions = this.data.fields[$field.data('id')] || null,
			hasPermissions = ($.isPlainObject(fieldPermissions) && Object.keys(fieldPermissions).length);

		if( hasPermissions ){
			// Show an icon on each field bar if permissions have been set.
			fldElement.setHasPermissionsIcon();

			// Loop the permissions and add hidden inputs
			for(var userGroupHandle in fieldPermissions){
				for(var type in fieldPermissions[userGroupHandle]){
					var hasPermission = fieldPermissions[userGroupHandle][type];
					self.addFieldInput($field, type, userGroupHandle, hasPermission);
				}
			}
		} else {
			fldElement.setHasNoPermissionsIcon();
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
	showSpinnerIcon($el){
		var $spinner = $el.find('.js--fab-spinner');

		if( $spinner.length ){
			$spinner.show();
		} else {
			$el.children().first().before('<div class="fab-inline fab-spinner spinner icon js--fab-spinner"/>');
		}
	},

	/**
	 * Hides a tab spinner icon
	 */
	hideSpinnerIcon($el){
		var $spinner = $el.find('.js--fab-spinner');
		if( $spinner.length ){
			$spinner.hide();
		}
	},

	/**
	 * Returns the permissions icon element
	 * @author Josh Smith <me@joshsmith.dev>
	 * @return object
	 */
	getHasPermissionsIconEl() {
		return $(`<a href="#" class="icon fab-icon ${this.getHasPermissionsIconClass()} js--fab-icon"/>`);
	},

	/**
	 * Returns the no permissions icon element
	 * @author Josh Smith <me@joshsmith.dev>
	 * @return object
	 */
	getHasNoPermissionsIconEl() {
		return $(`<a href="#" class="icon fab-icon ${this.getHasNoPermissionsIconClass()} js--fab-icon"/>`);
	},

	/**
	 * Returns the permissions icon class
	 * @author Josh Smith <me@joshsmith.dev>
	 * @return string
	 */
	getHasPermissionsIconClass() {
		return 'locked';
	},

	/**
	 * Returns the no permissions icon class
	 * @author Josh Smith <me@joshsmith.dev>
	 * @return string
	 */
	getHasNoPermissionsIconClass() {
		return 'unlocked';
	},

	/**
	 * Shows a tab fab permissions icon
	 */
	setHasPermissionsIcon($el){
		var $fabIcon = $el.find('.js--fab-icon'),
			permissionsIconClass = this.getHasPermissionsIconClass(),
			noPermissionsIconClass = this.getHasNoPermissionsIconClass();

		if( $fabIcon.length ){
			$fabIcon.removeClass(noPermissionsIconClass).addClass(permissionsIconClass);
		} else {
			$el.prepend(this.getHasPermissionsIconEl());
		}
	},

	/**
	 * Hides a tab fab permissions icon
	 */
	setHasNoPermissionsIcon($el){
		var $fabIcon = $el.find('.js--fab-icon'),
			permissionsIconClass = this.getHasPermissionsIconClass(),
			noPermissionsIconClass = this.getHasNoPermissionsIconClass();

		if( $fabIcon.length ){
			$fabIcon.removeClass(permissionsIconClass).addClass(noPermissionsIconClass);
		} else {
			$el.prepend(this.getHasNoPermissionsIconEl());
		}
	},

	/**
	 * Adds a tab hidden input to the DOM
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object}  $tab       jQuery collection
	 * * @param  {string}  type     Permission type, either canView or canEdit
	 * @param  {string}  handle     User group handle
	 * @param  {Boolean} value 		Tab input value
	 */
	addTabInput($tab, type, handle, value){
		value = typeof value === 'boolean' ? String(+value) : value; // Convert boolean values to string
		$tab.append(`<input class="fab-id-input js--fab-tab-input" type="hidden" name="${this._getTabInputName($tab)}[${handle}][${type}]" value="${value}">`);
	},

	/**
	 * Removes all fab permissions hidden inputs for the given tab
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {void}
	 */
	removeTabInputs($tab){
		$tab.find('.js--fab-tab-input').remove();
	},

	/**
	 * Adds a field hidden input to the DOM
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object}  $field     jQuery collection
	 * @param  {string}  type       Permission type, either canView or canEdit
	 * @param  {string}  handle     User group handle
	 * @param  {Boolean} value 		Field input value
	 */
	addFieldInput($field, type, handle, value){
		value = typeof value === 'boolean' ? String(+value) : value; // Convert boolean values to string
		$field.append('<input class="fab-id-input js--fab-field-input" type="hidden" data-id="'+$field.data('id')+'" data-handle="'+handle+'" data-type="'+type+'" name="fieldPermissions['+$field.data('id')+']['+handle+']['+type+']" value="'+value+'">');
	},

	/**
	 * Removes all fab permissions hidden inputs for the given field
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $field jQuery collection
	 * @return {void}
	 */
	removeFieldInputs($field){
		$field.find('.js--fab-field-input').remove();
	},

	/**
	 * Returns the fab permissions hidden field input name
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {string}
	 */
	_getTabInputName($tab){
		return `tabPermissions[${this._getTabName($tab)}]`;
		// return Craft.FieldLayoutDesigner.prototype.getElementPlacementInputName.call(FieldLayoutDesigner, this._getTabName($tab));
	},

	/**
	 * Returns the current tab name
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {string}      Tab name
	 */
	_getTabName($tab){
		var $labelSpan = $tab.find('.tabs .tab span');
		return $labelSpan.text();
	},

	/**
	 * Returns the original tab name, from the tab's data
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} $tab jQuery collection
	 * @return {string}      Original tab name
	 */
	_getOriginalTabName($tab){
		return $tab.data('fabPermissions').originalName || '';
	},

	/**
	 * Returns whether a particular permission is set or not
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {string}  type 	Type of permission to check
	 * @param  {string}  handle User group handle
	 * @return {Boolean}
	 */
	isPermissionSet($tab, type, handle){
		var $permissions = $tab.find('.js--fab-tab-input');
		if( ! $permissions.length ) return true;

		var self = this,
			matchedHandle = false;

		// Loop each permission input and return whether a permission is set
		$permissions.each(function(i, input){

			var $input = $(input),
				regexp = new RegExp(self.escapeRegExp(self._getTabInputName($tab)+'['+handle+']['+type+']')),
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
	isFieldPermissionSet($field, type, handle){
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
	escapeRegExp (string) {
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
	FieldLayoutDesigner = this;

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
	$menu.find('ul').eq(1).prepend($menuOption);
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
var initElement = Craft.FieldLayoutDesigner.prototype.initElement;
Craft.FieldLayoutDesigner.prototype.initElement = function($container) {
	initElement.call(this, $container);

	// Store the field element data
	var fldElement = $container.data().fldElement;
	if( !fldElement.isField ) return;

	FabPermissions.initElement($container);
};

/**
 * Sets the permissions icon on an element field
 * @author Josh Smith <me@joshsmith.dev>
 */
Craft.FieldLayoutDesigner.Element.prototype.setHasPermissionsIcon = function() {
	var $fabIcon = this.$container.find('.js--fab-icon'),
		permissionsIconClass = FabPermissions.getHasPermissionsIconClass(),
		noPermissionsIconClass = FabPermissions.getHasNoPermissionsIconClass();

	if( $fabIcon.length ) {
		$fabIcon.removeClass(noPermissionsIconClass).addClass(permissionsIconClass);
	} else {
		FabPermissions.getHasPermissionsIconEl().prependTo(this.$container);
	}
};

/**
 * Sets the no permissions icon on an element field
 * @author Josh Smith <me@joshsmith.dev>
 */
Craft.FieldLayoutDesigner.Element.prototype.setHasNoPermissionsIcon = function() {
	var $fabIcon = this.$container.find('.js--fab-icon'),
		permissionsIconClass = FabPermissions.getHasPermissionsIconClass(),
		noPermissionsIconClass = FabPermissions.getHasNoPermissionsIconClass();

	if( $fabIcon.length ) {
		$fabIcon.removeClass(permissionsIconClass).addClass(noPermissionsIconClass);
	} else {
		FabPermissions.getHasNoPermissionsIconEl().prependTo(this.$container);
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

	init(settings) {

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

	titleFormat(text){
		if( typeof text !== 'string' ) return '';
		return text[0].toUpperCase()+text.slice(1);
	},

	getTableHeadings(){
		return $(
			'<th scope="col" style="min-width: 50%;">User Group(s)</th>' +
			'<th scope="col">Can View</th>' +
			'<th scope="col">Can Edit</th>'
		);
	},

	handleSubmit(e){
		throw Error('handleSubmit must not be called directly.');
	},

	/**
	 * Populates the user group checkboxes into the modal
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {array} userGroups  An array of user groups
	 * @return {void}
	 */
	_populateUserGroups(userGroups){

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
	_handleOnPermissionClick($checkbox, value) {
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
	_createTableRow(rowData){
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

	_isPermissionSet($el, type, handle){
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
	handleSubmit(e){
		e.preventDefault();

		// Remove existing selections
		FabPermissions.removeTabInputs(this.settings.$el);

		var self = this;

		// Loop through each non-disabled checkbox option, and render hidden inputs into the DOM.
		this.$form.find('.tableview input[type="checkbox"]').each(function(i, checkbox){

			var $checkbox = $(checkbox),
				tabName = self.settings.$el.data('fabPermissions').originalName,
				userGroup = $checkbox.val(),
				permission = $checkbox.data('type');

			// Attempt to pull out the ID
			var id = (FabPermissions.data.tabs[tabName] &&
				FabPermissions.data.tabs[tabName][userGroup] &&
				FabPermissions.data.tabs[tabName][userGroup].id ?
			FabPermissions.data.tabs[tabName][userGroup].id : null);

			// Append the hidden input
			FabPermissions.addTabInput(self.settings.$el, permission, userGroup, $checkbox.is(':checked'));

			// Repopulate UID
			if( id != null ){
				FabPermissions.addTabInput(self.settings.$el, 'id', userGroup, id);
			}
		});

		FabPermissions.setHasPermissionsIcon(self.settings.$el.find('.tab'));

		return this.hide();
	},

	/**
	 * Clears selected permissions
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} e Event object
	 * @return {void}
	 */
	clearPermissions(e){
		FabPermissions.removeTabInputs(this.settings.$el);
		FabPermissions.setHasNoPermissionsIcon(this.settings.$el);
		this.hide();
	},

	getTableHeadings(){
		return $(
			'<th scope="col">User Group(s)</th>' +
			'<th scope="col">Can View</th>'
		);
	},

	_createTableRow(rowData){
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
	 * Returns the Field Element
	 * @author Josh Smith <me@joshsmith.dev>
	 * @return Element
	 */
	getFldElement() {
		return this.settings.$el.data().fldElement;
	},

	/**
	 * Handles the form submission.
	 * This populates hidden inputs with the selected user group handles.
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} e Event object
	 * @return {void}
	 */
	handleSubmit(e){
		e.preventDefault();

		// Remove existing selections
		FabPermissions.removeFieldInputs(this.settings.$el);

		var self = this;

		// Loop through each non-disabled checkbox option, and render hidden inputs into the DOM.
		this.$form.find('.tableview input[type="checkbox"]').each(function(i, checkbox){

			var $checkbox = $(checkbox),
				fieldId = self.settings.$el.data('id'),
				userGroup = $checkbox.val(),
				permission = $checkbox.data('type');

			// Attempt to pull out the ID
			var id = (FabPermissions.data.fields[fieldId] &&
				FabPermissions.data.fields[fieldId][userGroup] &&
				FabPermissions.data.fields[fieldId][userGroup].id ?
			FabPermissions.data.fields[fieldId][userGroup].id : null);

			// Append the hidden input
			FabPermissions.addFieldInput(self.settings.$el, $checkbox.data('type'), $checkbox.val(), $checkbox.is(':checked'));

			// Repopulate UID
			if( id != null ){
				FabPermissions.addFieldInput(self.settings.$el, 'id', userGroup, id);
			}
		});

		this.getFldElement().setHasPermissionsIcon();

		return this.hide();
	},

	/**
	 * Clears selected permissions
	 * @author Josh Smith <josh.smith@platocreative.co.nz>
	 * @param  {object} e Event object
	 * @return {void}
	 */
	clearPermissions(e){
		FabPermissions.removeFieldInputs(this.settings.$el);
		this.getFldElement().setHasNoPermissionsIcon();
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
	_isPermissionSet($el, type, handle){
		return FabPermissions.isFieldPermissionSet($el, type, handle);
	}
});
