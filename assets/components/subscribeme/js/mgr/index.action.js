/**
 * SubscribeMe
 *
 * Copyright 2011 by Mark Hamstra <business@markhamstra.nl>
 *
 * This file is part of SubscribeMe, a subscriptions management extra for MODX Revolution
 *
 * SubscribeMe is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * SubscribeMe is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * SubscribeMe; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
*/

Ext.onReady(function() {
    Ext.QuickTips.init();
    MODx.load({ xtype: 'sm-page-index'});
});

/*
Index page configuration.
 */
SM.page.Index = function(config) {
    config = config || {};
    Ext.applyIf(config,{
        renderTo: 'subscribeme',
        cls:'container',
        baseCls:'modx-formpanel',
        components: [{
            xtype: 'sm-panel-header'
        },{
            xtype: 'modx-tabs',
            width: '98%',
            border: true,
            defaults: {
                border: false,
                autoHeight: true,
                cls:'main-wrapper'
            },
            items: [{
                title: _('users'),
                items: [{
                    xtype: 'sm-grid-subscribers'
                }]
            },{
                title: _('sm.subscriptions'),
                items: [{
                    xtype: 'sm-grid-subscriptions'
                }]
            },{
                title: _('sm.transactions'),
                items: [{
                    xtype: 'sm-grid-transactions'
                }]
            },{
                title: _('sm.products'),
                items: [{
                    xtype: 'sm-grid-products'
                }]
            }]

        }]
    });
    SM.page.Index.superclass.constructor.call(this,config);
};
Ext.extend(SM.page.Index,MODx.Component);
Ext.reg('sm-page-index',SM.page.Index);

/*
Index page header configuration.
 */
SM.panel.Header = function(config) {
    config = config || {};
    Ext.apply(config,{
        border: false
        ,baseCls: 'modx-page-header'
        ,items: [{
            html: '<h2>'+_('subscribeme')+'</h2>'
            ,border: false
        }]
    });
    SM.panel.Header.superclass.constructor.call(this,config);
};
Ext.extend(SM.panel.Header,MODx.Panel);
Ext.reg('sm-panel-header',SM.panel.Header);



