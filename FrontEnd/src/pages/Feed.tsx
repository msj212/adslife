import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { TrendingUp, Zap, Clock, SlidersHorizontal, WifiOff } from 'lucide-react';
import NearbyDropdown from '../components/NearbyDropdown';
import SpotlightHero from '../components/SpotlightHero';
import OfferCard from '../components/OfferCard';
import { useFeedStore } from '../store/useFeedStore';
import { useUserStore } from '../store/useUserStore';
import { useGeolocation } from '../hooks/useGeolocation';
import { useInfiniteScroll } from '../hooks/useInfiniteScroll';
import { useOfflineOffers } from '../hooks/useOfflineOffers';
import { api, endpoints } from '../utils/api';
import { useFeedSync } from '../hooks/useRealtimeSync';
import { useCallback } from 'react';

interface Category { id: number; name: string; slug: string; icon: string; }

const FILTER_TABS = [
  { key: 'all',       label: 'All',         icon: null },
  { key: 'trending',  label: 'Trending',    icon: TrendingUp },
  { key: 'flash',     label: 'Flash Sales', icon: Zap },
  { key: 'ending',    label: 'Ending Soon', icon: Clock },
];

export default function Feed() {
  const { user } = useUserStore();
  const { lat, lng } = useGeolocation();
  const {
    forYouOffers, trendingOffers, loading, hasMore,
    activeTab, setTab, loadFeed, loadTrending,
  } = useFeedStore();
  const { isOnline, cachedOffers, lastSynced, cacheOffers } = useOfflineOffers();

  const [activeCategory, setActiveCategory] = useState<string | null>(null);
  const [activeFilter, setActiveFilter] = useState('all');
  const [nearbyRadius, setNearbyRadius] = useState(0);
  const [categories, setCategories] = useState<Category[]>([]);

  useEffect(() => {
    api.get(endpoints.categoriesList(true))
      .then(r => setCategories(r.data.data.categories ?? []))
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!isOnline) return;
    if (activeTab === 'forYou' && user) {
      loadFeed(user.id, lat, lng, true);
    } else {
      loadTrending('Chennai');
    }
  }, [activeTab, lat, lng, user?.id, isOnline]);

  useEffect(() => {
    if (forYouOffers.length > 0) cacheOffers(forYouOffers.slice(0, 50));
  }, [forYouOffers]);

  const loadMore = () => {
    if (activeTab === 'forYou' && user) loadFeed(user.id, lat, lng);
  };

  // Real-time: refresh feed when any vendor publishes a new offer
  const onNewOffer = useCallback(() => {
    if (!isOnline) return;
    if (activeTab === 'forYou' && user) loadFeed(user.id, lat, lng, true);
    else loadTrending('Chennai');
  }, [activeTab, user?.id, lat, lng, isOnline]);
  useFeedSync(onNewOffer);

  const sentinelRef = useInfiniteScroll(loadMore, hasMore && activeTab === 'forYou');

  const baseOffers = !isOnline
    ? cachedOffers
    : activeTab === 'forYou' ? forYouOffers : trendingOffers;

  const displayOffers = baseOffers.filter(o => {
    if (activeCategory && o.category !== activeCategory) return false;
    if (nearbyRadius > 0 && (o.distance === undefined || o.distance > nearbyRadius)) return false;
    if (activeFilter === 'flash' && (o.discountPercent ?? 0) < 30) return false;
    if (activeFilter === 'ending') {
      if (!o.validUntil) return false;
      const diff = new Date(o.validUntil).getTime() - Date.now();
      if (diff > 86400000 * 2) return false;
    }
    return true;
  });

  return (
    <div className="pb-20 sm:pb-6">

      {/* Offline banner */}
      {!isOnline && (
        <div className="mb-4 bg-[#FFFBEB] border border-[#FDE68A] rounded-xl px-4 py-2.5 flex items-center gap-2 text-[#78350F] text-sm">
          <WifiOff size={16} />
          <span>Offline mode — showing cached offers</span>
          {lastSynced && (
            <span className="text-[#92400E] ml-auto text-xs">
              Last synced {new Date(lastSynced).toLocaleTimeString()}
            </span>
          )}
        </div>
      )}

      {/* Hero — spotlight video carousel */}
      <SpotlightHero onExplore={() => setTab('trending')} />

      {/* Browse Categories */}
      <div className="mb-6">
        <h2 className="font-heading font-bold text-[var(--text)] text-lg mb-4">Browse Categories</h2>
        <div className="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-12 gap-2">
          {categories.map((cat) => (
            <button
              key={cat.slug}
              onClick={() => setActiveCategory(activeCategory === cat.slug ? null : cat.slug)}
              className={`category-chip ${activeCategory === cat.slug ? 'active' : ''}`}
            >
              <span className="text-2xl">{cat.icon}</span>
              <span className="text-[10px] font-medium text-[var(--text-secondary)] text-center leading-tight">{cat.name}</span>
            </button>
          ))}
        </div>
      </div>

      {/* Filter tabs + sort */}
      <div className="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div className="flex items-center gap-2 flex-wrap">
          {FILTER_TABS.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              onClick={() => setActiveFilter(key)}
              className={`filter-tab ${activeFilter === key ? 'active' : ''}`}
            >
              {Icon && <Icon size={13} />}
              {label}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-2">
          <NearbyDropdown radius={nearbyRadius} onChange={setNearbyRadius} />
          <button
            onClick={() => setTab(activeTab === 'forYou' ? 'trending' : 'forYou')}
            className="filter-tab"
          >
            <SlidersHorizontal size={13} />
            {activeTab === 'forYou' ? 'For You' : 'Trending'}
          </button>
        </div>
      </div>

      {/* Offers heading */}
      <div className="flex items-center gap-2 mb-4">
        <h2 className="font-heading font-bold text-[var(--text)] text-lg">All Offers</h2>
        <span className="badge badge-primary">{displayOffers.length} offers</span>
      </div>

      {/* Offers grid */}
      {displayOffers.length === 0 && !loading ? (
        <div className="text-center py-16 text-[var(--text-muted)]">
          <div className="text-5xl mb-4">🎁</div>
          <p className="font-heading font-semibold text-[var(--text-secondary)] mb-1">No offers found</p>
          <p className="text-sm">Try a different category or filter</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {displayOffers.map((offer, i) => (
            <motion.div
              key={offer.id}
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.03 }}
            >
              <OfferCard offer={offer} />
            </motion.div>
          ))}
        </div>
      )}

      {/* Skeleton */}
      {loading && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mt-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="card overflow-hidden">
              <div className="skeleton h-44 w-full rounded-none" />
              <div className="p-4 space-y-2">
                <div className="skeleton h-4 w-3/4" />
                <div className="skeleton h-3 w-1/2" />
                <div className="skeleton h-6 w-1/3" />
              </div>
            </div>
          ))}
        </div>
      )}

      <div ref={sentinelRef} className="h-4" />
    </div>
  );
}
