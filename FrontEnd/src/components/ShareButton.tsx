import { useState } from 'react';
import { Share2, Check } from 'lucide-react';
import type { Offer } from '../types';
import { api, endpoints } from '../utils/api';
import { useUserStore } from '../store/useUserStore';
import toast from 'react-hot-toast';

interface Props { offer: Offer }

export default function ShareButton({ offer }: Props) {
  const { user } = useUserStore();
  const [copied, setCopied] = useState(false);

  const handleShare = async (e: React.MouseEvent) => {
    e.stopPropagation();
    const url  = `${window.location.origin}/offer/${offer.id}`;
    const text = `${offer.discountPercent}% off at ${offer.businessName}! Check it out on AdsLife`;

    let platform = 'web';
    if (navigator.share) {
      try {
        await navigator.share({ title: offer.title, text, url });
        platform = 'native';
      } catch {}
    } else {
      await navigator.clipboard.writeText(`${text}\n${url}`);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
      toast.success('Link copied!');
      platform = 'clipboard';
    }

    if (user) {
      api.post(endpoints.shareTrack, { offer_id: offer.id, user_id: user.id, platform }).catch(() => {});
    }
  };

  return (
    <button
      onClick={handleShare}
      className="p-1.5 rounded-full text-gray-400 hover:text-primary hover:bg-primary/10 transition-all"
    >
      {copied ? <Check size={16} className="text-accent" /> : <Share2 size={16} />}
    </button>
  );
}
