import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';

export default class AdManagementPage extends ExtensionPage {
    oninit(vnode) {
        super.oninit(vnode);
        this.activeTab = 'ads';
        this.loading = true;
        this.ads = [];
        this.includedUsers = [];
        this.zones = [];
        this.editingAd = null;
        this.editingZone = null;
        this.analyticsAdId = null;
        this.analyticsData = null;
        this.analyticsPeriod = '30d';
        this.adsFilter = 'all'; // 'all', 'pending_review', 'active', 'inactive'
        this.loadData();
    }

    loadData() {
        this.loading = true;
        Promise.all([
            app.request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/advertisements' }),
            app.request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/ad-zones' }),
        ]).then(([adsResponse, zonesResponse]) => {
            this.ads = adsResponse.data || [];
            this.includedUsers = (adsResponse.included || []).filter(r => r.type === 'users');
            this.zones = zonesResponse.data || [];
            this.loading = false;
            m.redraw();
        }).catch(() => {
            this.loading = false;
            m.redraw();
        });
    }

    content() {
        if (this.loading) {
            return <div className="ExtensionPage-settings"><div className="container"><LoadingIndicator /></div></div>;
        }

        return (
            <div className="ExtensionPage-settings AdManagementPage">
                <div className="container">
                    <div className="AdManagement-tabs">
                        {['ads', 'zones', 'settings', 'analytics'].map(tab => (
                            <Button
                                className={'Button ' + (this.activeTab === tab ? 'Button--primary' : '')}
                                onclick={() => { this.activeTab = tab; this.editingAd = null; this.editingZone = null; }}
                            >
                                {app.translator.trans('ralkage-ad-management.admin.tabs.' + tab)}
                            </Button>
                        ))}
                    </div>

                    <div className="AdManagement-content">
                        {this.activeTab === 'ads' && this.adsTab()}
                        {this.activeTab === 'zones' && this.zonesTab()}
                        {this.activeTab === 'settings' && this.settingsTab()}
                        {this.activeTab === 'analytics' && this.analyticsTab()}
                    </div>
                </div>
            </div>
        );
    }

    adsTab() {
        if (this.editingAd !== null) {
            return this.adForm();
        }

        const pendingCount = this.ads.filter(ad => (ad.attributes.status || '') === 'pending_review').length;

        const filteredAds = this.adsFilter === 'all'
            ? this.ads
            : this.ads.filter(ad => (ad.attributes.status || (ad.attributes.isActive ? 'active' : 'inactive')) === this.adsFilter);

        return (
            <div className="AdManagement-section">
                <div className="AdManagement-header">
                    <h3>
                        {app.translator.trans('ralkage-ad-management.admin.ads.title')}
                        {pendingCount > 0 && (
                            <span className="AdBadge AdBadge--pending" style="margin-left: 8px;">
                                {app.translator.trans('ralkage-ad-management.admin.ads.pending_badge', { count: pendingCount })}
                            </span>
                        )}
                    </h3>
                    <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.editAd({})}>
                        {app.translator.trans('ralkage-ad-management.admin.ads.create')}
                    </Button>
                </div>

                <div className="AdManagement-filters">
                    {['all', 'active', 'pending_review', 'inactive', 'rejected'].map(filter => (
                        <Button
                            className={'Button Button--text ' + (this.adsFilter === filter ? 'active' : '')}
                            onclick={() => { this.adsFilter = filter; }}
                        >
                            {app.translator.trans('ralkage-ad-management.admin.ads.filter.' + filter)}
                        </Button>
                    ))}
                </div>

                <table className="AdManagement-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.name')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.type')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.zone')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.status')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.owner')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.stats.impressions')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.stats.clicks')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.ads.stats.ctr')}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {filteredAds.length === 0 && (
                            <tr><td colspan="10" className="AdManagement-empty">{app.translator.trans('ralkage-ad-management.admin.ads.empty')}</td></tr>
                        )}
                        {filteredAds.map(ad => {
                            const attrs = ad.attributes;
                            const status = attrs.status || (attrs.isActive ? 'active' : 'inactive');
                            const zone = this.zones.find(z => {
                                const rel = ad.relationships?.zone?.data;
                                return rel && z.id === rel.id;
                            });
                            const ownerRel = ad.relationships?.owner?.data;
                            const ownerUser = ownerRel && (this.includedUsers || []).find(u => u.id === ownerRel.id);

                            return (
                                <tr key={ad.id} className={status === 'pending_review' ? 'AdManagement-row--pending' : ''}>
                                    <td>{ad.id}</td>
                                    <td>
                                        {attrs.name}
                                        {attrs.pendingImageUrl && (
                                            <span className="AdBadge AdBadge--pending" style="margin-left: 6px;" title={app.translator.trans('ralkage-ad-management.admin.ads.pending_image_badge')}>
                                                {app.translator.trans('ralkage-ad-management.admin.ads.pending_image_badge')}
                                            </span>
                                        )}
                                    </td>
                                    <td><span className={'AdBadge AdBadge--' + attrs.type}>{attrs.type}</span></td>
                                    <td>{zone ? zone.attributes.label : '—'}</td>
                                    <td>
                                        <span className={'AdBadge AdBadge--status AdBadge--status-' + status}>
                                            {app.translator.trans('ralkage-ad-management.admin.ads.statuses.' + status)}
                                        </span>
                                    </td>
                                    <td>{ownerUser ? (ownerUser.attributes.displayName || ownerUser.attributes.username) : ownerRel ? '#' + ownerRel.id : '—'}</td>
                                    <td>{attrs.impressionsCount.toLocaleString()}</td>
                                    <td>{attrs.clicksCount.toLocaleString()}</td>
                                    <td>{attrs.ctr}%</td>
                                    <td className="AdManagement-actions">
                                        {status === 'pending_review' && (
                                            <Button className="Button Button--icon Button--success" icon="fas fa-check" title={app.translator.trans('ralkage-ad-management.admin.ads.approve')} onclick={() => this.approveAd(ad)} />
                                        )}
                                        {status === 'pending_review' && (
                                            <Button className="Button Button--icon Button--danger" icon="fas fa-times" title={app.translator.trans('ralkage-ad-management.admin.ads.reject')} onclick={() => this.rejectAd(ad)} />
                                        )}
                                        {attrs.pendingImageUrl && (
                                            <Button className="Button Button--icon Button--success" icon="fas fa-image" title={app.translator.trans('ralkage-ad-management.admin.ads.approve_image')} onclick={() => this.approveAdImage(ad)} />
                                        )}
                                        {attrs.pendingImageUrl && (
                                            <Button className="Button Button--icon Button--danger" icon="fas fa-ban" title={app.translator.trans('ralkage-ad-management.admin.ads.reject_image')} onclick={() => this.rejectAdImage(ad)} />
                                        )}
                                        <Button className="Button Button--icon" icon="fas fa-edit" onclick={() => this.editAd(ad)} />
                                        <Button className="Button Button--icon Button--danger" icon="fas fa-trash" onclick={() => this.deleteAd(ad)} />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        );
    }

    approveAd(ad) {
        app.request({
            method: 'PATCH',
            url: app.forum.attribute('apiUrl') + '/advertisements/' + ad.id,
            body: { data: { attributes: { status: 'active' } } },
        }).then(() => this.loadData());
    }

    rejectAd(ad) {
        app.request({
            method: 'PATCH',
            url: app.forum.attribute('apiUrl') + '/advertisements/' + ad.id,
            body: { data: { attributes: { status: 'rejected' } } },
        }).then(() => this.loadData());
    }

    approveAdImage(ad) {
        app.request({
            method: 'PATCH',
            url: app.forum.attribute('apiUrl') + '/advertisements/' + ad.id,
            body: { data: { attributes: { pending_image_action: 'approve' } } },
        }).then(() => this.loadData());
    }

    rejectAdImage(ad) {
        app.request({
            method: 'PATCH',
            url: app.forum.attribute('apiUrl') + '/advertisements/' + ad.id,
            body: { data: { attributes: { pending_image_action: 'reject' } } },
        }).then(() => this.loadData());
    }

    editAd(ad) {
        const attrs = ad.attributes || {};
        this.editingAd = {
            id: ad.id || null,
            name: attrs.name || '',
            type: attrs.type || 'image',
            zone_id: ad.relationships?.zone?.data?.id || (this.zones[0] ? this.zones[0].id : ''),
            content: attrs.content || '',
            image_url: attrs.imageUrl || '',
            link_url: attrs.linkUrl || '',
            alt_text: attrs.altText || '',
            width: attrs.width || '',
            height: attrs.height || '',
            is_active: attrs.isActive !== undefined ? attrs.isActive : true,
            start_date: attrs.startDate ? attrs.startDate.substring(0, 16) : '',
            end_date: attrs.endDate ? attrs.endDate.substring(0, 16) : '',
            priority: attrs.priority || 0,
            group_visibility: attrs.groupVisibility ? attrs.groupVisibility.join(',') : '',
            max_impressions: attrs.maxImpressions || '',
            max_clicks: attrs.maxClicks || '',
            max_image_changes: attrs.maxImageChanges || '',
            user_id: attrs.userId || '',
        };
    }

    adForm() {
        const ad = this.editingAd;
        const isNew = !ad.id;

        return (
            <div className="AdManagement-form">
                <h3>{app.translator.trans('ralkage-ad-management.admin.ads.' + (isNew ? 'create' : 'edit'))}</h3>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.ads.name')}</label>
                    <input className="FormControl" type="text" value={ad.name} oninput={e => { ad.name = e.target.value; }} />
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.ads.type')}</label>
                    <select className="FormControl" value={ad.type} onchange={e => { ad.type = e.target.value; }}>
                        <option value="image">{app.translator.trans('ralkage-ad-management.admin.ads.types.image')}</option>
                        <option value="html">{app.translator.trans('ralkage-ad-management.admin.ads.types.html')}</option>
                        <option value="adsense">{app.translator.trans('ralkage-ad-management.admin.ads.types.adsense')}</option>
                    </select>
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.ads.zone')}</label>
                    <select className="FormControl" value={ad.zone_id} onchange={e => { ad.zone_id = e.target.value; }}>
                        <option value="">{app.translator.trans('ralkage-ad-management.admin.ads.select_zone')}</option>
                        {this.zones.map(zone => (
                            <option value={zone.id}>{zone.attributes.label} ({zone.attributes.position})</option>
                        ))}
                    </select>
                </div>

                {(ad.type === 'html' || ad.type === 'adsense') && (
                    <div className="Form-group">
                        <label>{app.translator.trans('ralkage-ad-management.admin.ads.content')}</label>
                        <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.ads.content_help')}</p>
                        <textarea className="FormControl" rows="6" value={ad.content} oninput={e => { ad.content = e.target.value; }} />
                    </div>
                )}

                {ad.type === 'image' && (
                    <div>
                        <div className="Form-group">
                            <label>{app.translator.trans('ralkage-ad-management.admin.ads.image_url')}</label>
                            <input className="FormControl" type="text" value={ad.image_url} oninput={e => { ad.image_url = e.target.value; }} />
                        </div>
                        <div className="Form-group">
                            <label>{app.translator.trans('ralkage-ad-management.admin.ads.link_url')}</label>
                            <input className="FormControl" type="text" value={ad.link_url} oninput={e => { ad.link_url = e.target.value; }} />
                        </div>
                        <div className="Form-group">
                            <label>{app.translator.trans('ralkage-ad-management.admin.ads.alt_text')}</label>
                            <input className="FormControl" type="text" value={ad.alt_text} oninput={e => { ad.alt_text = e.target.value; }} />
                        </div>
                        <div className="Form-group Form-group--inline">
                            <div>
                                <label>{app.translator.trans('ralkage-ad-management.admin.ads.width')}</label>
                                <input className="FormControl" type="number" value={ad.width} oninput={e => { ad.width = e.target.value; }} />
                            </div>
                            <div>
                                <label>{app.translator.trans('ralkage-ad-management.admin.ads.height')}</label>
                                <input className="FormControl" type="number" value={ad.height} oninput={e => { ad.height = e.target.value; }} />
                            </div>
                        </div>
                    </div>
                )}

                <div className="Form-group">
                    <Switch state={ad.is_active} onchange={value => { ad.is_active = value; }}>
                        {app.translator.trans('ralkage-ad-management.admin.ads.is_active')}
                    </Switch>
                </div>

                <div className="Form-group Form-group--inline">
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.ads.start_date')}</label>
                        <input className="FormControl" type="datetime-local" value={ad.start_date} oninput={e => { ad.start_date = e.target.value; }} />
                    </div>
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.ads.end_date')}</label>
                        <input className="FormControl" type="datetime-local" value={ad.end_date} oninput={e => { ad.end_date = e.target.value; }} />
                    </div>
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.ads.priority')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.ads.priority_help')}</p>
                    <input className="FormControl" type="number" value={ad.priority} oninput={e => { ad.priority = parseInt(e.target.value) || 0; }} />
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.ads.group_visibility')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.ads.group_visibility_help')}</p>
                    <input className="FormControl" type="text" value={ad.group_visibility} oninput={e => { ad.group_visibility = e.target.value; }} />
                </div>

                <div className="Form-group Form-group--inline">
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.ads.max_impressions')}</label>
                        <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.ads.max_impressions_help')}</p>
                        <input className="FormControl" type="number" value={ad.max_impressions} oninput={e => { ad.max_impressions = e.target.value; }} />
                    </div>
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.ads.max_clicks')}</label>
                        <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.ads.max_clicks_help')}</p>
                        <input className="FormControl" type="number" value={ad.max_clicks} oninput={e => { ad.max_clicks = e.target.value; }} />
                    </div>
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.ads.max_image_changes')}</label>
                    <input className="FormControl" type="number" value={ad.max_image_changes} oninput={e => { ad.max_image_changes = e.target.value; }} />
                </div>

                <div className="Form-group AdManagement-formButtons">
                    <Button className="Button" onclick={() => { this.editingAd = null; }}>
                        {app.translator.trans('ralkage-ad-management.admin.cancel')}
                    </Button>
                    <Button className="Button Button--primary" onclick={() => this.saveAd()} loading={this.saving}>
                        {app.translator.trans('ralkage-ad-management.admin.save')}
                    </Button>
                </div>
            </div>
        );
    }

    saveAd() {
        const ad = this.editingAd;
        const isNew = !ad.id;

        const attributes = {
            name: ad.name,
            type: ad.type,
            zone_id: parseInt(ad.zone_id),
            content: ad.content || null,
            image_url: ad.image_url || null,
            link_url: ad.link_url || null,
            alt_text: ad.alt_text || null,
            width: ad.width ? parseInt(ad.width) : null,
            height: ad.height ? parseInt(ad.height) : null,
            is_active: ad.is_active,
            start_date: ad.start_date || null,
            end_date: ad.end_date || null,
            priority: parseInt(ad.priority) || 0,
            group_visibility: ad.group_visibility ? ad.group_visibility.split(',').map(g => parseInt(g.trim())).filter(Boolean) : null,
            max_impressions: ad.max_impressions ? parseInt(ad.max_impressions) : null,
            max_clicks: ad.max_clicks ? parseInt(ad.max_clicks) : null,
            max_image_changes: ad.max_image_changes ? parseInt(ad.max_image_changes) : null,
        };

        this.saving = true;

        app.request({
            method: isNew ? 'POST' : 'PATCH',
            url: app.forum.attribute('apiUrl') + '/advertisements' + (isNew ? '' : '/' + ad.id),
            body: { data: { attributes } },
        }).then(() => {
            this.editingAd = null;
            this.saving = false;
            this.loadData();
        }).catch(() => {
            this.saving = false;
            m.redraw();
        });
    }

    deleteAd(ad) {
        if (!confirm(app.translator.trans('ralkage-ad-management.admin.ads.confirm_delete'))) return;

        app.request({
            method: 'DELETE',
            url: app.forum.attribute('apiUrl') + '/advertisements/' + ad.id,
        }).then(() => this.loadData());
    }

    zonesTab() {
        if (this.editingZone !== null) {
            return this.zoneForm();
        }

        return (
            <div className="AdManagement-section">
                <div className="AdManagement-header">
                    <h3>{app.translator.trans('ralkage-ad-management.admin.zones.title')}</h3>
                    <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.editZone({})}>
                        {app.translator.trans('ralkage-ad-management.admin.zones.create')}
                    </Button>
                </div>
                <table className="AdManagement-table">
                    <thead>
                        <tr>
                            <th>{app.translator.trans('ralkage-ad-management.admin.zones.label')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.zones.name')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.zones.position')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.zones.is_active')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.zones.ads_count')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.zones.dimensions')}</th>
                            <th>{app.translator.trans('ralkage-ad-management.admin.zones.display_mode')}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {this.zones.map(zone => {
                            const attrs = zone.attributes;
                            return (
                                <tr key={zone.id}>
                                    <td>
                                        {attrs.label}
                                        {attrs.isDefault && <span className="AdBadge AdBadge--default"> {app.translator.trans('ralkage-ad-management.admin.zones.default_badge')}</span>}
                                    </td>
                                    <td><code>{attrs.name}</code></td>
                                    <td>{app.translator.trans('ralkage-ad-management.admin.zones.positions.' + attrs.position) || attrs.position}</td>
                                    <td>{attrs.isActive ? '✓' : '✗'}</td>
                                    <td>{attrs.adsCount}</td>
                                    <td>{attrs.maxWidth && attrs.maxHeight ? attrs.maxWidth + '×' + attrs.maxHeight : '—'}</td>
                                    <td>{app.translator.trans('ralkage-ad-management.admin.zones.display_modes.' + (attrs.displayMode || 'rotate'))}</td>
                                    <td className="AdManagement-actions">
                                        <Button className="Button Button--icon" icon="fas fa-edit" onclick={() => this.editZone(zone)} />
                                        {!attrs.isDefault && (
                                            <Button className="Button Button--icon Button--danger" icon="fas fa-trash" onclick={() => this.deleteZone(zone)} />
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                <div className="AdManagement-zoneInfo">
                    <p><strong>{app.translator.trans('ralkage-ad-management.admin.zones.shortcode_title')}</strong> <code>{'{myadvertisements[zone_name]}'}</code></p>
                    <p>{app.translator.trans('ralkage-ad-management.admin.zones.shortcode_help')}</p>
                </div>
            </div>
        );
    }

    editZone(zone) {
        const attrs = zone.attributes || {};
        this.editingZone = {
            id: zone.id || null,
            name: attrs.name || '',
            label: attrs.label || '',
            description: attrs.description || '',
            position: attrs.position || 'custom',
            is_active: attrs.isActive !== undefined ? attrs.isActive : true,
            sort_order: attrs.sortOrder || 0,
            max_width: attrs.maxWidth || '',
            max_height: attrs.maxHeight || '',
            display_mode: attrs.displayMode || 'rotate',
            is_default: attrs.isDefault || false,
        };
    }

    zoneForm() {
        const zone = this.editingZone;
        const isNew = !zone.id;

        return (
            <div className="AdManagement-form">
                <h3>{app.translator.trans('ralkage-ad-management.admin.zones.' + (isNew ? 'create' : 'edit'))}</h3>

                {!zone.is_default && (
                    <div className="Form-group">
                        <label>{app.translator.trans('ralkage-ad-management.admin.zones.name')}</label>
                        <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.zones.name_help')}</p>
                        <input className="FormControl" type="text" value={zone.name} oninput={e => { zone.name = e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'); }} />
                    </div>
                )}

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.zones.label')}</label>
                    <input className="FormControl" type="text" value={zone.label} oninput={e => { zone.label = e.target.value; }} />
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.zones.description')}</label>
                    <textarea className="FormControl" rows="3" value={zone.description} oninput={e => { zone.description = e.target.value; }} />
                </div>

                {!zone.is_default && (
                    <div className="Form-group">
                        <label>{app.translator.trans('ralkage-ad-management.admin.zones.position')}</label>
                        <select className="FormControl" value={zone.position} onchange={e => { zone.position = e.target.value; }}>
                            {['header', 'below_header', 'between_posts', 'sidebar', 'above_footer', 'footer', 'custom'].map(pos => (
                                <option value={pos}>{app.translator.trans('ralkage-ad-management.admin.zones.positions.' + pos)}</option>
                            ))}
                        </select>
                    </div>
                )}

                <div className="Form-group">
                    <Switch state={zone.is_active} onchange={value => { zone.is_active = value; }}>
                        {app.translator.trans('ralkage-ad-management.admin.zones.is_active')}
                    </Switch>
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.zones.sort_order')}</label>
                    <input className="FormControl" type="number" value={zone.sort_order} oninput={e => { zone.sort_order = parseInt(e.target.value) || 0; }} />
                </div>

                <div className="Form-group Form-group--inline">
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.zones.max_width')}</label>
                        <input className="FormControl" type="number" value={zone.max_width} oninput={e => { zone.max_width = e.target.value; }} />
                    </div>
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.zones.max_height')}</label>
                        <input className="FormControl" type="number" value={zone.max_height} oninput={e => { zone.max_height = e.target.value; }} />
                    </div>
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.zones.display_mode')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.zones.display_mode_help')}</p>
                    <select className="FormControl" value={zone.display_mode} onchange={e => { zone.display_mode = e.target.value; }}>
                        <option value="rotate">{app.translator.trans('ralkage-ad-management.admin.zones.display_modes.rotate')}</option>
                        <option value="stack">{app.translator.trans('ralkage-ad-management.admin.zones.display_modes.stack')}</option>
                    </select>
                </div>

                <div className="Form-group AdManagement-formButtons">
                    <Button className="Button" onclick={() => { this.editingZone = null; }}>{app.translator.trans('ralkage-ad-management.admin.cancel')}</Button>
                    <Button className="Button Button--primary" onclick={() => this.saveZone()} loading={this.savingZone}>{app.translator.trans('ralkage-ad-management.admin.save')}</Button>
                </div>
            </div>
        );
    }

    saveZone() {
        const zone = this.editingZone;
        const isNew = !zone.id;

        const attributes = {
            name: zone.name,
            label: zone.label,
            description: zone.description || null,
            position: zone.position,
            isActive: zone.is_active,
            sortOrder: parseInt(zone.sort_order) || 0,
            maxWidth: zone.max_width ? parseInt(zone.max_width) : null,
            maxHeight: zone.max_height ? parseInt(zone.max_height) : null,
            displayMode: zone.display_mode,
        };

        this.savingZone = true;

        app.request({
            method: isNew ? 'POST' : 'PATCH',
            url: app.forum.attribute('apiUrl') + '/ad-zones' + (isNew ? '' : '/' + zone.id),
            body: { data: { attributes } },
        }).then(() => {
            this.editingZone = null;
            this.savingZone = false;
            this.loadData();
        }).catch(() => {
            this.savingZone = false;
            m.redraw();
        });
    }

    deleteZone(zone) {
        if (!confirm(app.translator.trans('ralkage-ad-management.admin.zones.confirm_delete'))) return;

        app.request({
            method: 'DELETE',
            url: app.forum.attribute('apiUrl') + '/ad-zones/' + zone.id,
        }).then(() => this.loadData());
    }

    settingsTab() {
        return (
            <div className="AdManagement-section">
                <h3>{app.translator.trans('ralkage-ad-management.admin.tabs.settings')}</h3>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.between_posts_interval')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.between_posts_interval_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.between_posts_interval',
                        type: 'number',
                    })}
                </div>

                <div className="Form-group">
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.show_sponsored_label',
                        type: 'boolean',
                        label: app.translator.trans('ralkage-ad-management.admin.settings.show_sponsored_label'),
                        help: app.translator.trans('ralkage-ad-management.admin.settings.show_sponsored_label_help'),
                    })}
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.sponsored_label_text')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.sponsored_label_text_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.sponsored_label_text',
                        type: 'text',
                    })}
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.default_max_image_changes')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.default_max_image_changes_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.default_max_image_changes',
                        type: 'number',
                    })}
                </div>

                <div className="Form-group">
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.track_impressions',
                        type: 'boolean',
                        label: app.translator.trans('ralkage-ad-management.admin.settings.track_impressions'),
                        help: app.translator.trans('ralkage-ad-management.admin.settings.track_impressions_help'),
                    })}
                </div>

                <div className="Form-group">
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.track_clicks',
                        type: 'boolean',
                        label: app.translator.trans('ralkage-ad-management.admin.settings.track_clicks'),
                        help: app.translator.trans('ralkage-ad-management.admin.settings.track_clicks_help'),
                    })}
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.adsense_publisher_id')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.adsense_publisher_id_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.adsense_publisher_id',
                        type: 'text',
                    })}
                </div>

                <h4 style="margin-top: 30px; padding-top: 16px; border-top: 1px solid #e8ecf0;">{app.translator.trans('ralkage-ad-management.admin.settings.image_settings_title')}</h4>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.allowed_image_formats')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.allowed_image_formats_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.allowed_image_formats',
                        type: 'text',
                    })}
                </div>

                <div className="Form-group">
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.enable_compression',
                        type: 'boolean',
                        label: app.translator.trans('ralkage-ad-management.admin.settings.enable_compression'),
                        help: app.translator.trans('ralkage-ad-management.admin.settings.enable_compression_help'),
                    })}
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.compression_quality')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.compression_quality_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.compression_quality',
                        type: 'number',
                    })}
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.compression_method')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.compression_method_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.compression_method',
                        type: 'select',
                        options: { gd: 'PHP GD (built-in)', resmush: 'reSmush.it API (lossless)' },
                    })}
                </div>

                <div className="Form-group">
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.require_image_approval',
                        type: 'boolean',
                        label: app.translator.trans('ralkage-ad-management.admin.settings.require_image_approval'),
                        help: app.translator.trans('ralkage-ad-management.admin.settings.require_image_approval_help'),
                    })}
                </div>

                <h4 style="margin-top: 30px; padding-top: 16px; border-top: 1px solid #e8ecf0;">{app.translator.trans('ralkage-ad-management.admin.settings.notifications_title')}</h4>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.expiration_reminder_days')}</label>
                    <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.expiration_reminder_days_help')}</p>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.expiration_reminder_days',
                        type: 'number',
                    })}
                </div>

                <div className="Form-group">
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.send_performance_reports',
                        type: 'boolean',
                        label: app.translator.trans('ralkage-ad-management.admin.settings.send_performance_reports'),
                        help: app.translator.trans('ralkage-ad-management.admin.settings.send_performance_reports_help'),
                    })}
                </div>

                <div className="Form-group AdManagement-zoneInfo">
                    <p>{app.translator.trans('ralkage-ad-management.admin.settings.notifications_info')}</p>
                </div>

                <h4 style="margin-top: 24px;">{app.translator.trans('ralkage-ad-management.admin.settings.expiration_templates_title')}</h4>
                <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.templates_placeholders_expiry')}</p>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.expiration_subject_template')}</label>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.expiration_subject_template',
                        type: 'text',
                    })}
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.expiration_body_template')}</label>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.expiration_body_template',
                        type: 'textarea',
                    })}
                </div>

                <h4 style="margin-top: 24px;">{app.translator.trans('ralkage-ad-management.admin.settings.performance_templates_title')}</h4>
                <p className="helpText">{app.translator.trans('ralkage-ad-management.admin.settings.templates_placeholders_performance')}</p>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.performance_subject_template')}</label>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.performance_subject_template',
                        type: 'text',
                    })}
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.admin.settings.performance_body_template')}</label>
                    {this.buildSettingComponent({
                        setting: 'ralkage-ad-management.performance_body_template',
                        type: 'textarea',
                    })}
                </div>

                {this.submitButton()}
            </div>
        );
    }

    analyticsTab() {
        return (
            <div className="AdManagement-section">
                <h3>{app.translator.trans('ralkage-ad-management.admin.analytics.title')}</h3>

                <div className="Form-group Form-group--inline">
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.analytics.select_ad')}</label>
                        <select className="FormControl" value={this.analyticsAdId || ''} onchange={e => { this.analyticsAdId = e.target.value; this.loadAnalytics(); }}>
                            <option value="">{app.translator.trans('ralkage-ad-management.admin.analytics.select_ad')}</option>
                            {this.ads.map(ad => (
                                <option value={ad.id}>{ad.attributes.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label>{app.translator.trans('ralkage-ad-management.admin.analytics.period')}</label>
                        <select className="FormControl" value={this.analyticsPeriod} onchange={e => { this.analyticsPeriod = e.target.value; this.loadAnalytics(); }}>
                            <option value="7d">{app.translator.trans('ralkage-ad-management.admin.analytics.periods.7d')}</option>
                            <option value="30d">{app.translator.trans('ralkage-ad-management.admin.analytics.periods.30d')}</option>
                            <option value="90d">{app.translator.trans('ralkage-ad-management.admin.analytics.periods.90d')}</option>
                        </select>
                    </div>
                </div>

                {this.analyticsData && (
                    <div className="AdManagement-analytics">
                        <div className="AdAnalytics-cards">
                            <div className="AdAnalytics-card">
                                <div className="AdAnalytics-card-value">{this.analyticsData.total_impressions.toLocaleString()}</div>
                                <div className="AdAnalytics-card-label">{app.translator.trans('ralkage-ad-management.admin.analytics.total_impressions')}</div>
                            </div>
                            <div className="AdAnalytics-card">
                                <div className="AdAnalytics-card-value">{this.analyticsData.total_clicks.toLocaleString()}</div>
                                <div className="AdAnalytics-card-label">{app.translator.trans('ralkage-ad-management.admin.analytics.total_clicks')}</div>
                            </div>
                            <div className="AdAnalytics-card">
                                <div className="AdAnalytics-card-value">{(this.analyticsData.period_clicks || 0).toLocaleString()}</div>
                                <div className="AdAnalytics-card-label">{app.translator.trans('ralkage-ad-management.admin.analytics.period_clicks')}</div>
                            </div>
                            <div className="AdAnalytics-card">
                                <div className="AdAnalytics-card-value">{this.analyticsData.ctr}%</div>
                                <div className="AdAnalytics-card-label">{app.translator.trans('ralkage-ad-management.admin.analytics.ctr')}</div>
                            </div>
                        </div>

                        {Object.keys(this.analyticsData.clicks_by_day).length > 0 ? (
                            <div className="AdAnalytics-chart">
                                <h4>{app.translator.trans('ralkage-ad-management.admin.analytics.clicks_by_day')}</h4>
                                <table className="AdManagement-table">
                                    <thead>
                                        <tr><th>{app.translator.trans('ralkage-ad-management.admin.analytics.date')}</th><th>{app.translator.trans('ralkage-ad-management.admin.analytics.clicks_header')}</th></tr>
                                    </thead>
                                    <tbody>
                                        {Object.entries(this.analyticsData.clicks_by_day).map(([date, count]) => (
                                            <tr key={date}><td>{date}</td><td>{count}</td></tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="AdManagement-empty">{app.translator.trans('ralkage-ad-management.admin.analytics.no_data')}</p>
                        )}
                    </div>
                )}
            </div>
        );
    }

    loadAnalytics() {
        if (!this.analyticsAdId) {
            this.analyticsData = null;
            return;
        }

        app.request({
            method: 'GET',
            url: app.forum.attribute('apiUrl') + '/advertisements/' + this.analyticsAdId + '/analytics',
            params: { period: this.analyticsPeriod },
        }).then(response => {
            this.analyticsData = response.data;
            m.redraw();
        }).catch(() => {
            this.analyticsData = null;
            m.redraw();
        });
    }
}
