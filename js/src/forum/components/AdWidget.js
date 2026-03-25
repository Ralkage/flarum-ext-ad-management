import Component from 'flarum/common/Component';
import AdBanner from './AdBanner';

export default class AdWidget extends Component {
    view() {
        const ads = this.attrs.ads || [];
        if (!ads.length) return null;

        return (
            <div className="AdWidget">
                {ads.map(ad => (
                    <AdBanner key={ad.id} ad={ad} className="AdBanner--sidebar" />
                ))}
            </div>
        );
    }
}
