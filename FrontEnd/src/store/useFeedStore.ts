import { create } from 'zustand';
import type { Offer } from '../types';
import { api, endpoints } from '../utils/api';

interface FeedState {
  forYouOffers: Offer[];
  trendingOffers: Offer[];
  page: number;
  hasMore: boolean;
  loading: boolean;
  error: string | null;
  activeTab: 'forYou' | 'trending';
  city: string;

  setTab: (tab: 'forYou' | 'trending') => void;
  loadFeed: (userId: number, lat: number, lng: number, reset?: boolean) => Promise<void>;
  loadTrending: (city: string) => Promise<void>;
  recordInteraction: (userId: number, offerId: number, action: string) => Promise<void>;
}

export const useFeedStore = create<FeedState>((set, get) => ({
  forYouOffers:    [],
  trendingOffers:  [],
  page:            1,
  hasMore:         true,
  loading:         false,
  error:           null,
  activeTab:       'forYou',
  city:            'Chennai',

  setTab: (tab) => set({ activeTab: tab }),

  loadFeed: async (userId, lat, lng, reset = false) => {
    const { page, loading } = get();
    if (loading) return;
    set({ loading: true, error: null });

    const currentPage = reset ? 1 : page;

    try {
      const res = await api.get(endpoints.feed(userId, lat, lng, currentPage));
      if (res.data.success) {
        const offers = mapOffers(res.data.data);
        set((s) => ({
          forYouOffers: reset ? offers : [...s.forYouOffers, ...offers],
          page:         currentPage + 1,
          hasMore:      offers.length >= 20,
          loading:      false,
        }));
      }
    } catch (err: any) {
      set({ error: err.message, loading: false });
    }
  },

  loadTrending: async (city) => {
    set({ loading: true });
    try {
      const res = await api.get(endpoints.trending(city));
      if (res.data.success) {
        set({ trendingOffers: mapOffers(res.data.data), loading: false, city });
      }
    } catch {
      set({ loading: false });
    }
  },

  recordInteraction: async (userId, offerId, action) => {
    try {
      await api.post(endpoints.interaction, { user_id: userId, offer_id: offerId, action });
    } catch {}
  },
}));

function mapOffers(raw: any[]): Offer[] {
  return raw.map((o: any) => ({
    id:                 o.id,
    vendorId:           o.vendor_id,
    title:              o.title,
    description:        o.description,
    category:           o.category,
    discountPercent:    parseFloat(o.discount_percent) || 0,
    originalPrice:      parseFloat(o.original_price)   || 0,
    offerPrice:         parseFloat(o.offer_price)       || 0,
    imageUrl:           o.image_url,
    bannerUrl:          o.banner_url,
    couponCode:         o.coupon_code,
    maxRedemptions:     parseInt(o.max_redemptions)    || 0,
    currentRedemptions: parseInt(o.current_redemptions)|| 0,
    validFrom:          o.valid_from,
    validUntil:         o.valid_until,
    isFeatured:         !!o.is_featured,
    isActive:           !!o.is_active,
    views:              parseInt(o.views) || 0,
    clicks:             parseInt(o.clicks)|| 0,
    saves:              parseInt(o.saves) || 0,
    createdAt:          o.created_at,
    videoUrl:           o.video_url || undefined,
    businessName:       o.business_name,
    vendorLogo:         o.vendor_logo,
    vendorCity:         o.vendor_city,
    vendorAddress:      o.vendor_address || undefined,
    vendorLat:          o.vlat ? Number.parseFloat(o.vlat) : undefined,
    vendorLng:          o.vlng ? Number.parseFloat(o.vlng) : undefined,
    vendorPhone:        o.vendor_phone || undefined,
    vendorEmail:        o.vendor_email || undefined,
    vendorWebsite:      o.vendor_website || undefined,
    distance:           o.distance === undefined ? undefined : Number.parseFloat(o.distance),
    score:              Number.parseFloat(o.score) || undefined,
  }));
}
