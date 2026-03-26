import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';

export default class MyAdsPage extends UserPage {
    oninit(vnode) {
        super.oninit(vnode);
        this.loading = true;
        this.ads = [];
        this.zones = [];
        this.showSubmitForm = false;
        this.submitting = false;
        this.newAd = null;
        this.loadUser(m.route.param('username'));
    }

    show(user) {
        super.show(user);
        this.loadAds();
    }

    loadAds() {
        this.loading = true;
        const canSubmit = app.forum.attribute('canSubmitAds');

        const requests = [
            app.request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/advertisements' }),
        ];

        if (canSubmit) {
            requests.push(
                app.request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/ad-zones' })
            );
        }

        Promise.all(requests).then(([adsResponse, zonesResponse]) => {
            this.ads = adsResponse.data || [];
            this.zones = zonesResponse ? (zonesResponse.data || []) : [];
            this.loading = false;
            m.redraw();
        }).catch(() => {
            this.loading = false;
            m.redraw();
        });
    }

    content() {
        if (this.loading) {
            return <div className="MyAdsPage"><LoadingIndicator /></div>;
        }

        return (
            <div className="MyAdsPage">
                <div className="MyAds-header">
                    <h2>{app.translator.trans('ralkage-ad-management.forum.page.title')}</h2>
                    {app.forum.attribute('canSubmitAds') && !this.showSubmitForm && (
                        <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.openSubmitForm()}>
                            {app.translator.trans('ralkage-ad-management.forum.page.submit_ad')}
                        </Button>
                    )}
                </div>

                {this.showSubmitForm && this.submitForm()}

                {!this.showSubmitForm && this.ads.length === 0 ? (
                    <div className="MyAds-empty">
                        <p>{app.translator.trans('ralkage-ad-management.forum.page.empty')}</p>
                    </div>
                ) : (
                    <div className="MyAds-list">
                        {this.ads.map(ad => this.adCard(ad))}
                    </div>
                )}
            </div>
        );
    }

    openSubmitForm() {
        this.newAd = {
            name: '',
            image_url: '',
            link_url: '',
            alt_text: '',
            zone_id: this.zones[0] ? this.zones[0].id : '',
            width: '',
            height: '',
        };
        this.showSubmitForm = true;
    }

    submitForm() {
        const ad = this.newAd;

        return (
            <div className="MyAds-submitForm">
                <h3>{app.translator.trans('ralkage-ad-management.forum.page.submit_ad')}</h3>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.forum.ads.name')}</label>
                    <input className="FormControl" type="text" value={ad.name}
                        oninput={e => { ad.name = e.target.value; }} />
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.forum.ads.zone')}</label>
                    <select className="FormControl" value={ad.zone_id}
                        onchange={e => { ad.zone_id = e.target.value; }}>
                        <option value="">{app.translator.trans('ralkage-ad-management.forum.ads.select_zone')}</option>
                        {this.zones.map(zone => (
                            <option value={zone.id}>{zone.attributes.label}</option>
                        ))}
                    </select>
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.forum.ads.image_url')}</label>
                    <input className="FormControl" type="text" value={ad.image_url}
                        oninput={e => { ad.image_url = e.target.value; }} />
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.forum.ads.link_url')}</label>
                    <input className="FormControl" type="text" value={ad.link_url}
                        oninput={e => { ad.link_url = e.target.value; }} />
                </div>

                <div className="Form-group">
                    <label>{app.translator.trans('ralkage-ad-management.forum.ads.alt_text')}</label>
                    <input className="FormControl" type="text" value={ad.alt_text}
                        oninput={e => { ad.alt_text = e.target.value; }} />
                </div>

                <p className="helpText">
                    {app.translator.trans('ralkage-ad-management.forum.page.submit_pending_notice')}
                </p>

                <div className="MyAds-formButtons">
                    <Button className="Button" onclick={() => { this.showSubmitForm = false; }}>
                        {app.translator.trans('ralkage-ad-management.forum.page.cancel')}
                    </Button>
                    <Button className="Button Button--primary" onclick={() => this.submitAd()} loading={this.submitting}>
                        {app.translator.trans('ralkage-ad-management.forum.page.submit_ad')}
                    </Button>
                </div>
            </div>
        );
    }

    submitAd() {
        const ad = this.newAd;
        if (!ad.name || !ad.image_url || !ad.zone_id) return;

        this.submitting = true;

        app.request({
            method: 'POST',
            url: app.forum.attribute('apiUrl') + '/advertisements',
            body: {
                data: {
                    attributes: {
                        name: ad.name,
                        type: 'image',
                        zone_id: parseInt(ad.zone_id),
                        image_url: ad.image_url,
                        link_url: ad.link_url || null,
                        alt_text: ad.alt_text || null,
                        width: ad.width ? parseInt(ad.width) : null,
                        height: ad.height ? parseInt(ad.height) : null,
                    },
                },
            },
        }).then(() => {
            this.submitting = false;
            this.showSubmitForm = false;
            this.loadAds();
        }).catch(error => {
            this.submitting = false;
            alert(error.response?.errors?.[0]?.detail || 'Failed to submit ad.');
            m.redraw();
        });
    }

    adCard(ad) {
        const attrs = ad.attributes;
        const status = attrs.status || (attrs.isActive ? 'active' : 'inactive');

        let statusClass, statusLabel;
        if (status === 'pending_review') {
            statusClass = 'pending';
            statusLabel = 'pending';
        } else if (status === 'rejected') {
            statusClass = 'rejected';
            statusLabel = 'rejected';
        } else if (attrs.isActive) {
            const now = new Date();
            if (attrs.startDate && new Date(attrs.startDate) > now) {
                statusClass = 'scheduled';
                statusLabel = 'scheduled';
            } else if (attrs.endDate && new Date(attrs.endDate) < now) {
                statusClass = 'expired';
                statusLabel = 'expired';
            } else {
                statusClass = 'active';
                statusLabel = 'active';
            }
        } else {
            statusClass = 'inactive';
            statusLabel = 'inactive';
        }

        return (
            <div className="MyAds-card" key={ad.id}>
                <div className="MyAds-card-header">
                    <h3>{attrs.name}</h3>
                    <span className={'MyAds-status MyAds-status--' + statusClass}>
                        {app.translator.trans('ralkage-ad-management.forum.page.status.' + statusLabel)}
                    </span>
                </div>

                {attrs.imageUrl && (
                    <div className="MyAds-card-preview">
                        <img src={attrs.imageUrl} alt={attrs.altText || attrs.name} />
                    </div>
                )}

                <div className="MyAds-card-stats">
                    <div className="MyAds-stat">
                        <i className="fas fa-eye"></i>
                        {app.translator.trans('ralkage-ad-management.forum.page.impressions', { count: attrs.impressionsCount.toLocaleString() })}
                    </div>
                    <div className="MyAds-stat">
                        <i className="fas fa-mouse-pointer"></i>
                        {app.translator.trans('ralkage-ad-management.forum.page.clicks', { count: attrs.clicksCount.toLocaleString() })}
                    </div>
                    <div className="MyAds-stat">
                        <i className="fas fa-chart-line"></i>
                        {app.translator.trans('ralkage-ad-management.forum.page.ctr', { value: attrs.ctr })}
                    </div>
                </div>

                {attrs.type === 'image' && (
                    <div className="MyAds-card-actions">
                        {attrs.maxImageChanges === null || attrs.imageChangesCount < attrs.maxImageChanges ? (
                            <div>
                                <Button className="Button Button--primary Button--small" icon="fas fa-image" onclick={() => this.changeImage(ad)}>
                                    {app.translator.trans('ralkage-ad-management.forum.page.edit_image')}
                                </Button>
                                {attrs.maxImageChanges !== null && (
                                    <span className="MyAds-changes-info">
                                        {app.translator.trans('ralkage-ad-management.forum.page.image_changes_remaining', {
                                            count: attrs.maxImageChanges - attrs.imageChangesCount,
                                        })}
                                    </span>
                                )}
                            </div>
                        ) : (
                            <span className="MyAds-changes-info MyAds-changes-info--exhausted">
                                {app.translator.trans('ralkage-ad-management.forum.page.no_changes_remaining')}
                            </span>
                        )}
                    </div>
                )}
            </div>
        );
    }

    changeImage(ad) {
        const newUrl = prompt('Enter new image URL:', ad.attributes.imageUrl);
        if (!newUrl || newUrl === ad.attributes.imageUrl) return;

        app.request({
            method: 'PATCH',
            url: app.forum.attribute('apiUrl') + '/advertisements/' + ad.id,
            body: { data: { attributes: { image_url: newUrl } } },
        }).then(() => {
            this.loadAds();
        }).catch(error => {
            alert(error.response?.errors?.[0]?.detail || 'Failed to update image.');
        });
    }
}
