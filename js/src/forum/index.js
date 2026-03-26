import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import CommentPost from 'flarum/forum/components/CommentPost';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import AdBanner from './components/AdBanner';
import AdWidget from './components/AdWidget';
import MyAdsPage from './components/MyAdsPage';

let adsCache = null;
let adsCacheTime = 0;
let adsLoading = false;
let adsError = false;
let zonePositions = {};
let zoneNames = {};
// One randomly selected ad per position/zone, chosen fresh on each cache load
let selectedAdByPosition = {};
let selectedAdByZoneName = {};
const CACHE_TTL = 60000;

function loadAds() {
    if (adsLoading || adsError) return;
    const now = Date.now();
    if (adsCache && (now - adsCacheTime) < CACHE_TTL) return;

    adsLoading = true;

    app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/advertisements/active',
        errorHandler: () => {},
    }).then(response => {
        adsCache = response.data || [];
        adsCacheTime = Date.now();
        adsLoading = false;
        adsError = false;

        // Build zone maps from included resources
        zonePositions = {};
        zoneNames = {};
        if (response.included) {
            response.included.forEach(item => {
                if (item.type === 'ad-zones') {
                    zonePositions[item.id] = item.attributes.position;
                    zoneNames[item.id] = item.attributes.name;
                }
            });
        }

        // Pick one random ad per position and per zone name for this page load
        selectAdsForRotation();

        m.redraw();
    }).catch(() => {
        adsLoading = false;
        adsError = true;
        // Retry after 30 seconds
        setTimeout(() => { adsError = false; loadAds(); }, 30000);
    });
}

/**
 * For each position and zone name, randomly select one ad to display.
 * This ensures rotation across page loads while remaining stable during a visit.
 */
function selectAdsForRotation() {
    selectedAdByPosition = {};
    selectedAdByZoneName = {};

    if (!adsCache) return;

    const adsByPosition = {};
    const adsByZoneName = {};

    adsCache.forEach(ad => {
        const zoneRel = ad.relationships?.zone?.data;
        if (!zoneRel) return;

        const pos = zonePositions[zoneRel.id];
        const name = zoneNames[zoneRel.id];

        if (pos) {
            if (!adsByPosition[pos]) adsByPosition[pos] = [];
            adsByPosition[pos].push(ad);
        }
        if (name) {
            if (!adsByZoneName[name]) adsByZoneName[name] = [];
            adsByZoneName[name].push(ad);
        }
    });

    Object.entries(adsByPosition).forEach(([pos, ads]) => {
        selectedAdByPosition[pos] = ads[Math.floor(Math.random() * ads.length)];
    });

    Object.entries(adsByZoneName).forEach(([name, ads]) => {
        selectedAdByZoneName[name] = ads[Math.floor(Math.random() * ads.length)];
    });
}

function getAdsByPosition(position) {
    if (!adsCache) return [];

    return adsCache.filter(ad => {
        const zoneRel = ad.relationships?.zone?.data;
        if (!zoneRel) return false;
        return zonePositions[zoneRel.id] === position;
    });
}

function mountAdPlaceholders(element) {
    if (!adsCache || !element) return;

    element.querySelectorAll('.AdZonePlaceholder[data-zone]').forEach(placeholder => {
        const zoneName = placeholder.getAttribute('data-zone');
        if (!zoneName) return;

        // Show one randomly-selected ad per zone
        const ad = selectedAdByZoneName[zoneName];
        if (ad) {
            m.render(placeholder, m(AdBanner, { key: ad.id, ad }));
        }
    });
}

function shouldHideAds() {
    return !!app.forum.attribute('adsHidden');
}

function renderZoneAds(position, className) {
    // Pick the pre-selected ad for this position (rotation)
    const ad = selectedAdByPosition[position];
    if (!ad) return null;

    return (
        <div className={'AdZone ' + className} key={'ad-' + position}>
            <div className="container">
                <AdBanner key={ad.id} ad={ad} />
            </div>
        </div>
    );
}

app.initializers.add('ralkage-ad-management', () => {
    app.routes['user.ads'] = { path: '/u/:username/ads', component: MyAdsPage };

    // Inject header ad via DOM since there's no good Mithril hook above the header
    let headerAdInjected = false;

    function injectHeaderAd() {
        if (headerAdInjected || shouldHideAds() || !adsCache) return;

        const ad = selectedAdByPosition['header'];
        if (!ad) return;

        const appHeader = document.getElementById('header');
        if (!appHeader) return;

        // Check if already injected
        if (document.querySelector('.AdZone--header')) return;
        headerAdInjected = true;

        const container = document.createElement('div');
        container.className = 'AdZone AdZone--header';

        const inner = document.createElement('div');
        inner.className = 'container';
        container.appendChild(inner);

        // Render the single selected ad into the container using Mithril
        appHeader.parentNode.insertBefore(container, appHeader);
        m.render(inner, m(AdBanner, { key: ad.id, ad }));
    }

    // Add "My Ads" link to user page nav
    extend(UserPage.prototype, 'navItems', function (items) {
        if (app.session.user && app.session.user === this.user) {
            items.add('ads',
                <LinkButton href={app.route('user.ads', { username: this.user.slug() })} icon="fas fa-ad">
                    {app.translator.trans('ralkage-ad-management.forum.nav.my_ads')}
                </LinkButton>,
                10
            );
        }
    });

    // Index page: below_header, above_footer, footer zones
    extend(IndexPage.prototype, 'view', function (vdom) {
        loadAds();
        if (shouldHideAds() || !adsCache || !vdom || !vdom.children) return;

        injectHeaderAd();

        // Below header - insert at position 0 (above hero)
        const belowHeader = renderZoneAds('below_header', 'AdZone--below-header');
        if (belowHeader) {
            const heroIdx = vdom.children.findIndex(c =>
                c && c.attrs && c.attrs.className && typeof c.attrs.className === 'string' && c.attrs.className.includes('Hero')
            );
            vdom.children.splice((heroIdx >= 0 ? heroIdx + 1 : 0), 0, belowHeader);
        }

        // Above footer
        const aboveFooter = renderZoneAds('above_footer', 'AdZone--above-footer');
        if (aboveFooter) vdom.children.push(aboveFooter);

        // Footer
        const footer = renderZoneAds('footer', 'AdZone--footer');
        if (footer) vdom.children.push(footer);
    });

    // Sidebar zone
    extend(IndexPage.prototype, 'sidebarItems', function (items) {
        loadAds();
        if (shouldHideAds() || !adsCache) return;

        const ad = selectedAdByPosition['sidebar'];
        if (ad) {
            items.add('adWidget', <AdWidget ads={[ad]} />, -100);
        }
    });

    // Shortcode placeholders: mount ads into {myadvertisements[zone_name]} divs in post content
    extend(CommentPost.prototype, 'oncreate', function () {
        mountAdPlaceholders(this.element);
    });

    extend(CommentPost.prototype, 'onupdate', function () {
        mountAdPlaceholders(this.element);
    });

    // Between posts zone: cycles through ads using post position
    extend(CommentPost.prototype, 'view', function (vdom) {
        loadAds();
        if (shouldHideAds() || !adsCache) return;

        const interval = app.forum.attribute('adsBetweenPostsInterval') || 5;
        if (interval <= 0) return;

        const ads = getAdsByPosition('between_posts');
        if (ads.length === 0) return;

        const post = this.attrs.post;
        if (!post) return;

        const number = post.number();
        if (number > 1 && (number - 1) % interval === 0) {
            // Use proper modulo to cycle through available ads (handles any number of ads)
            const n = Math.floor((number - 1) / interval) - 1;
            const adIndex = ((n % ads.length) + ads.length) % ads.length;
            const ad = ads[adIndex];
            if (ad && vdom && vdom.children) {
                vdom.children.push(
                    <div className="AdZone AdZone--between-posts">
                        <AdBanner ad={ad} />
                    </div>
                );
            }
        }
    });

    // Discussion page: below_header and footer zones
    extend(DiscussionPage.prototype, 'view', function (vdom) {
        loadAds();
        if (shouldHideAds() || !adsCache || !vdom || !vdom.children) return;

        injectHeaderAd();

        const belowHeader = renderZoneAds('below_header', 'AdZone--below-header');
        if (belowHeader) {
            vdom.children.unshift(belowHeader);
        }
    });
});
