import app from 'flarum/admin/app';
import AdManagementPage from './components/AdManagementPage';

app.initializers.add('ralkage-ad-management', () => {
    app.extensionData.for('ralkage-ad-management')
        .registerPage(AdManagementPage)
        .registerPermission(
            {
                icon: 'fas fa-ad',
                label: app.translator.trans('ralkage-ad-management.admin.permissions.submit_ad'),
                permission: 'ralkage-ad-management.submitAd',
                defaultGroup: 'member',
            },
            'reply',
            90
        );
});
