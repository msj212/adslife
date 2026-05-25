import axios from 'axios';

const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

export const api = axios.create({
  baseURL: BASE_URL,
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000,
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('adslife_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('adslife_token');
      localStorage.removeItem('adslife_user');
      globalThis.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export const endpoints = {
  // Auth
  login:        '/auth/login.php',
  register:     '/auth/register.php',
  googleAuth:   '/auth/google.php',

  // Feed
  feed:         (uid: number, lat: number, lng: number, page = 1) =>
    `/feed/personalized.php?user_id=${uid}&lat=${lat}&lng=${lng}&page=${page}`,
  trending:     (city: string, limit = 20) => `/feed/trending.php?city=${city}&limit=${limit}`,
  nearby:       (lat: number, lng: number, radius = 5) => `/feed/nearby.php?lat=${lat}&lng=${lng}&radius=${radius}`,
  offerDetail:  (id: number) => `/offers/detail.php?id=${id}`,
  offerCreate:  '/offers/create.php',
  offerUpdate:  '/offers/update.php',
  offerDelete:  '/offers/delete.php',
  myOffers:     '/offers/my-offers.php',
  interaction:  '/feed/interaction.php',

  // Streaks
  streakUpdate: '/streaks/update.php',
  streakStatus: (uid: number) => `/streaks/status.php?user_id=${uid}`,

  // Badges
  badgesCheck:  '/badges/check.php',
  badgesUser:   (uid: number) => `/badges/user.php?user_id=${uid}`,
  badgesAll:    '/badges/all.php',

  // Leaderboard
  leaderboard: (city: string, period: string, limit = 50) =>
    `/leaderboard/index.php?city=${city}&period=${period}&limit=${limit}`,

  // Analytics
  heatmap:    (vendorId: number, days = 30) => `/analytics/heatmap.php?vendor_id=${vendorId}&days=${days}`,
  audience:   (vendorId: number, days = 30) => `/analytics/audience.php?vendor_id=${vendorId}&days=${days}`,
  benchmark:  (vendorId: number) => `/analytics/benchmark.php?vendor_id=${vendorId}`,
  roi:        (offerId: number, days = 30) => `/analytics/roi.php?offer_id=${offerId}&days=${days}`,

  // A/B Test
  abCreate:   '/ab-test/create.php',
  abResults:  (testId: number) => `/ab-test/results.php?test_id=${testId}`,
  abConclude: '/ab-test/conclude.php',

  // Fraud
  fraudCheckVendor: '/fraud/check-vendor.php',
  fraudCheckOffer:  '/fraud/check-offer.php',
  fraudFlagged: (status?: string, type?: string) => {
    const s = status ? `status=${status}&` : '';
    const t = type   ? `type=${type}`      : '';
    return `/fraud/flagged.php?${s}${t}`;
  },
  fraudReview:      '/fraud/review.php',

  // Targeting
  targetingSet:     '/targeting/set.php',
  targetingResolve: (area: string) => `/targeting/resolve-area.php?area=${encodeURIComponent(area)}`,

  // Translation
  translateOffer:     '/translate/offer.php',
  translateLanguages: '/translate/languages.php',

  // Share
  shareTrack: '/share/track.php',

  // Group Deals
  groupDealsActive: (city: string) => `/group-deals/active.php?city=${city}`,
  groupDealJoin:    '/group-deals/join.php',
  groupDealStatus:  (dealId: number) => `/group-deals/status.php?deal_id=${dealId}`,

  // Spotlight
  spotlightActive:  '/spotlight/active.php',
  spotlightRequest: '/spotlight/request.php',
  spotlightList:    (status?: string) => '/spotlight/list.php' + (status ? `?status=${status}` : ''),
  spotlightApprove: '/spotlight/approve.php',

  // Notifications
  notificationsList:    (limit = 30) => `/notifications/list.php?limit=${limit}`,
  notificationsMarkRead: '/notifications/mark-read.php',
  saveFcmToken:          '/notifications/save-token.php',
  testPush:              '/notifications/test-push.php',

  // Become vendor
  becomeVendor: '/auth/become-vendor.php',
  uploadImage:  '/upload/image.php',

  // Budget Suggester
  budgetSuggest: (vendorId: number, goal: string, category: string) =>
    `/vendor/budget-suggest.php?vendor_id=${vendorId}&goal=${goal}&category=${category}`,

  // Plans
  plansList:   '/plans/list.php',
  plansManage: '/plans/manage.php',

  // Payment (Cashfree)
  paymentCreateOrder: '/payment/create-order.php',
  paymentVerify:      (orderId: string) => `/payment/verify.php?order_id=${orderId}`,

  // Vendor application
  vendorApplySubmit:  '/vendor-apply/submit.php',
  vendorMyPlan:       '/vendor/my-plan.php',
  vendorProfile:      '/vendor/profile.php',
  vendorFollow:       '/vendor/follow.php',
  vendorFollowers:    (vendorId: number, limit = 20) => `/vendor/followers.php?vendor_id=${vendorId}&limit=${limit}`,
  vendorFollowing:    '/vendor/following.php',
  savedOffers:        (limit = 30, offset = 0) => `/feed/saved.php?limit=${limit}&offset=${offset}`,
  savedIds:           '/feed/saved-ids.php',
  unsaveOffer:        '/feed/saved.php',
  vendorDashboard:    '/vendor/dashboard.php',

  // Admin – vendor requests
  adminVendorRequests: (status?: string) => status ? `/admin/vendor-requests.php?status=${status}` : '/admin/vendor-requests.php',
  adminReviewVendor:   '/admin/review-vendor.php',

  // Admin – super panel
  adminStats:      '/admin/stats.php',
  adminUsers:      (search = '', role = '', status = '', limit = 30, offset = 0) => {
    const p = new URLSearchParams();
    if (search) p.set('search', search);
    if (role)   p.set('role', role);
    if (status) p.set('status', status);
    p.set('limit', String(limit)); p.set('offset', String(offset));
    return `/admin/users.php?${p}`;
  },
  adminUserAction: '/admin/users.php',
  adminOffers:     (search = '', category = '', status = '', limit = 30, offset = 0) => {
    const p = new URLSearchParams();
    if (search)   p.set('search', search);
    if (category) p.set('category', category);
    if (status)   p.set('status', status);
    p.set('limit', String(limit)); p.set('offset', String(offset));
    return `/admin/offers.php?${p}`;
  },
  adminOfferAction: '/admin/offers.php',
  adminVendors:    (search = '', status = '', plan = '', limit = 30, offset = 0) => {
    const p = new URLSearchParams();
    if (search) p.set('search', search);
    if (status) p.set('status', status);
    if (plan)   p.set('plan', plan);
    p.set('limit', String(limit)); p.set('offset', String(offset));
    return `/admin/vendors.php?${p}`;
  },
  adminVendorAction: '/admin/vendors.php',
  adminSpotlight:    (status = '') => status ? `/admin/spotlight.php?status=${status}` : '/admin/spotlight.php',
  adminSpotlightAction: '/admin/spotlight.php',
  adminBroadcast:  '/admin/broadcast.php',

  // Support tickets
  supportCreate: '/support/create.php',
  supportList:   (status?: string) => status ? `/support/list.php?status=${status}` : '/support/list.php',
  supportReply:  '/support/reply.php',

  // Banner ads
  bannerRequest: '/banner-ads/request.php',
  bannerList:    '/banner-ads/list.php',
  bannerReview:  '/banner-ads/review.php',

  // Categories
  categoriesList:   (activeOnly = true) => `/categories/list.php?active_only=${activeOnly ? '1' : '0'}`,
  categoriesManage: '/categories/manage.php',

  // Site settings
  siteSettings: '/admin/site-settings/index.php',
};
