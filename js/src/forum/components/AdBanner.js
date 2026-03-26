import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';

export default class AdBanner extends Component {
    oninit(vnode) {
        super.oninit(vnode);
        this.tracked = false;
    }

    oncreate(vnode) {
        super.oncreate(vnode);
        this.trackImpression();
    }

    view() {
        const ad = this.attrs.ad;
        if (!ad) return null;

        const attrs = ad.attributes || ad;
        const className = 'AdBanner AdBanner--' + (attrs.type || 'image') + ' ' + (this.attrs.className || '');

        const showLabel = app.forum.attribute('adsShowSponsoredLabel') !== false;
        const customLabelText = app.forum.attribute('adsSponsoredLabelText');
        const labelText = customLabelText || app.translator.trans('ralkage-ad-management.forum.ad.sponsored');

        return (
            <div className={className} data-ad-id={ad.id}>
                {this.renderAd(attrs, ad.id)}
                {showLabel && (
                    <div className="AdBanner-label">
                        {labelText}
                    </div>
                )}
            </div>
        );
    }

    renderAd(attrs, adId) {
        if (attrs.type === 'image') {
            const imgAttrs = {
                src: attrs.imageUrl || attrs.image_url,
                alt: attrs.altText || attrs.alt_text || '',
                className: 'AdBanner-image',
            };

            if (attrs.width) imgAttrs.width = attrs.width;
            if (attrs.height) imgAttrs.height = attrs.height;

            const linkUrl = attrs.linkUrl || attrs.link_url;

            if (linkUrl) {
                return (
                    <a
                        href={linkUrl}
                        target="_blank"
                        rel="noopener nofollow sponsored"
                        onclick={() => this.trackClick(adId)}
                        className="AdBanner-link"
                    >
                        <img {...imgAttrs} />
                    </a>
                );
            }

            return <img {...imgAttrs} />;
        }

        if (attrs.type === 'html' || attrs.type === 'adsense') {
            return <div className="AdBanner-html" oncreate={vnode => {
                const container = vnode.dom;
                const content = attrs.content;

                // Parse content into a temporary element
                const temp = document.createElement('div');
                temp.innerHTML = content;

                // Collect scripts separately, add non-script nodes first
                const scripts = [];
                Array.from(temp.childNodes).forEach(node => {
                    if (node.nodeName === 'SCRIPT') {
                        scripts.push(node);
                    } else {
                        container.appendChild(node.cloneNode(true));
                    }
                });

                // Also grab scripts nested inside other elements
                Array.from(temp.querySelectorAll('script')).forEach(s => {
                    if (!scripts.includes(s)) scripts.push(s);
                });

                // For AdSense: load the library script first, then defer the push
                const libraryScripts = scripts.filter(s => s.src);
                const inlineScripts = scripts.filter(s => !s.src && s.textContent.trim());

                // Append external scripts (e.g. adsbygoogle.js)
                libraryScripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => {
                        newScript.setAttribute(attr.name, attr.value);
                    });
                    container.appendChild(newScript);
                });

                // Defer inline scripts (e.g. adsbygoogle.push) until the container has layout
                if (inlineScripts.length > 0) {
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            inlineScripts.forEach(oldScript => {
                                const newScript = document.createElement('script');
                                Array.from(oldScript.attributes).forEach(attr => {
                                    newScript.setAttribute(attr.name, attr.value);
                                });
                                newScript.textContent = oldScript.textContent;
                                container.appendChild(newScript);
                            });
                        }, 100);
                    });
                }
            }} />;
        }

        return null;
    }

    trackImpression() {
        if (this.tracked) return;
        this.tracked = true;

        const ad = this.attrs.ad;
        if (!ad || !app.forum.attribute('adsTrackImpressions')) return;

        app.request({
            method: 'POST',
            url: app.forum.attribute('apiUrl') + '/ad-track/impression',
            body: { data: { attributes: { adIds: [ad.id] } } },
        }).catch(() => {});
    }

    trackClick(adId) {
        if (!app.forum.attribute('adsTrackClicks')) return;

        app.request({
            method: 'POST',
            url: app.forum.attribute('apiUrl') + '/ad-track/click',
            body: { data: { attributes: { adId } } },
        }).catch(() => {});
    }
}
