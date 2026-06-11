<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\SiteContent;
use App\Models\Banner;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // ── FAQs ──────────────────────────────────────────────────────────
        $faqs = [
            ['question' => 'What is included in a hamper?', 'answer' => 'Each hamper is carefully curated with a selection of premium items chosen to suit the occasion. Contents vary by hamper, you can see exactly what is included on each product listing.'],
            ['question' => 'Do you offer free delivery?', 'answer' => 'Yes, we offer free delivery across the Maldives. Your hamper will arrive beautifully packaged and on time.'],
            ['question' => 'Can I personalise a hamper?', 'answer' => 'Absolutely. We offer personalised hampers where you can add a custom message, choose specific items, or tailor the hamper to the recipient. Contact us or use the Personalised Hampers section.'],
            ['question' => 'Do you handle corporate orders?', 'answer' => 'Yes, we handle corporate gifting of all sizes. Whether it is 10 hampers or 500, we can accommodate bulk orders with custom branding and messaging. Reach out via the Corporate Orders page.'],
            ['question' => 'How far in advance should I order?', 'answer' => 'We recommend ordering at least 2–3 days in advance for standard orders. For large corporate or personalised orders, please give us at least 5–7 days notice.'],
            ['question' => 'What payment methods do you accept?', 'answer' => 'We accept bank transfers and cash on delivery. Payment details will be confirmed when we get back to you after your order is placed.'],
            ['question' => 'Can I add a gift message?', 'answer' => 'Yes, you can include a personalised gift message with any hamper. Simply add your message in the notes field when placing your order.'],
            ['question' => 'What if an item is out of stock?', 'answer' => 'If an item in your chosen hamper is unavailable, we will contact you to offer a suitable replacement of equal or greater value.'],
        ];

        foreach ($faqs as $i => $faq) {
            Faq::firstOrCreate(
                ['question' => $faq['question']],
                ['answer' => $faq['answer'], 'sort_order' => $i, 'is_active' => true]
            );
        }

        // ── About Us content ──────────────────────────────────────────────
        $aboutContent = [
            'about_hero_subtitle'  => 'Where every gift is crafted with intention, presented with elegance, and felt long after it is given.',
            'about_who_label'      => 'Who We Are',
            'about_section_title'  => 'The Art of Refined Gifting',
            'about_story_p1'       => 'Aura Gifts was born from a simple belief, that a truly great gift should feel personal, look beautiful, and leave a lasting impression. Based in the Maldives, we specialise in luxury hampers that are thoughtfully curated and beautifully presented.',
            'about_story_p2'       => 'Every hamper we create tells a story. From the items we select to the way we wrap and present them, every detail is handled with care. Whether it is for a birthday, a wedding, a corporate client, or just because, we believe the act of giving deserves to feel as special as the occasion itself.',
            'about_story_p3'       => 'We work with individuals and businesses alike, offering instant hampers, personalised orders, build-your-own options, and large-scale corporate gifting. No matter the size of the order, our standard never changes.',
            'about_cta_title'      => 'Ready to Send Something Special?',
            'about_cta_subtitle'   => 'Browse our collection or reach out for a personalised or corporate order.',
        ];

        foreach ($aboutContent as $key => $value) {
            SiteContent::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'content_type' => 'text']
            );
        }

        // ── Hero content ──────────────────────────────────────────────────
        SiteContent::updateOrCreate(
            ['key' => 'top_bar_text'],
            ['value' => 'FOR QUERIES PLEASE CALL 9992070', 'content_type' => 'text']
        );
        SiteContent::updateOrCreate(
            ['key' => 'hero_title'],
            ['value' => 'Thoughtfully Curated, Beautifully Delivered', 'content_type' => 'text']
        );
        SiteContent::updateOrCreate(
            ['key' => 'hero_subtitle'],
            ['value' => 'Luxury hampers for every occasion. Personalised, corporate, or ready to gift, delivered across the Maldives.', 'content_type' => 'text']
        );

        // ── Terms of Service ──────────────────────────────────────────────
        $terms = [
            ['heading' => '1. Orders and Payment', 'body' => 'All orders placed through Aura Gifts are subject to availability and confirmation. We will contact you after your order is received to confirm details and arrange payment. We accept bank transfers and cash on delivery. Your order is only confirmed once payment has been received.'],
            ['heading' => '2. Delivery', 'body' => 'We offer free delivery across the Maldives. Delivery times are estimated and may vary depending on location and order volume. We will confirm your delivery window when your order is confirmed.'],
            ['heading' => '3. Personalised and Corporate Orders', 'body' => 'Personalised and corporate orders require additional lead time. We recommend contacting us at least 5–7 days before your required delivery date. Custom orders are non-refundable once production has begun.'],
            ['heading' => '4. Cancellations and Refunds', 'body' => 'Cancellations must be requested before your order goes into preparation. Once an order is being prepared or has been dispatched, we are unable to offer a refund. If there is an issue with your order, please contact us within 24 hours of delivery.'],
            ['heading' => '5. Product Substitutions', 'body' => 'In the rare event that a product within a hamper is unavailable, we reserve the right to substitute it with an item of equal or greater value. We will inform you of any substitutions before dispatch where possible.'],
            ['heading' => '6. Contact', 'body' => 'For any questions regarding your order, please contact us at aura.gifts.mv@gmail.com or call us at 9992070.'],
        ];

        SiteContent::updateOrCreate(
            ['key' => 'terms_of_service'],
            ['value' => json_encode($terms), 'content_type' => 'json']
        );

        // ── Privacy Policy ────────────────────────────────────────────────
        $privacy = [
            ['heading' => '1. Information We Collect', 'body' => 'When you place an order with Aura Gifts, we collect your name, email address, phone number, and delivery details. We use this information solely to process and deliver your order.'],
            ['heading' => '2. How We Use Your Information', 'body' => 'Your personal information is used to confirm your order, arrange delivery, and communicate with you about your purchase. We do not sell, rent, or share your personal information with third parties for marketing purposes.'],
            ['heading' => '3. Data Storage', 'body' => 'Your order information is stored securely and retained only as long as necessary for the purposes outlined in this policy or as required by applicable law.'],
            ['heading' => '4. Cookies', 'body' => 'Our website may use cookies to improve your browsing experience. These cookies do not collect personally identifiable information and can be disabled through your browser settings.'],
            ['heading' => '5. Your Rights', 'body' => 'You have the right to request access to, correction of, or deletion of your personal information. To make such a request, please contact us at aura.gifts.mv@gmail.com.'],
            ['heading' => '6. Contact', 'body' => 'If you have any questions about this privacy policy or how we handle your data, please contact us at aura.gifts.mv@gmail.com or call us at 9992070.'],
        ];

        SiteContent::updateOrCreate(
            ['key' => 'privacy_policy'],
            ['value' => json_encode($privacy), 'content_type' => 'json']
        );

        // ── Logos ─────────────────────────────────────────────────────────────
        SiteContent::updateOrCreate(
            ['key' => 'logo_square'],
            ['value' => 'https://res.cloudinary.com/dat7p3xuv/image/upload/v1780717074/aura-gifts/logos/hky5k3sgrupvx5jtxqrh.jpg', 'content_type' => 'text']
        );
        SiteContent::updateOrCreate(
            ['key' => 'logo_wide'],
            ['value' => 'https://res.cloudinary.com/dat7p3xuv/image/upload/v1780717076/aura-gifts/logos/tdpoqlfhze5zkuvdppgw.jpg', 'content_type' => 'text']
        );

        // ── Personalised Hampers page ─────────────────────────────────────
        $personalisedContent = [
            'personalised_hero_label'   => 'Personalised Hampers',
            'personalised_hero_heading' => 'A Gift Made Just for Them',
            'personalised_hero_sub'     => 'Tell us the occasion, the person, and the budget. We will do the rest.',
            'personalised_card1_title'  => 'Personalised for the Occasion',
            'personalised_card1_body'   => 'Whether it is a birthday, anniversary, wedding, or just because, we craft a hamper that fits the moment perfectly.',
            'personalised_card2_title'  => 'You Choose the Theme',
            'personalised_card2_body'   => 'Pick a colour palette, a mood, or a set of items you love. We bring it to life with our signature presentation.',
            'personalised_card3_title'  => 'We Handle Everything',
            'personalised_card3_body'   => 'Just send us a message and we take care of the rest. From curation to delivery, it is all sorted.',
            'personalised_cta_title'    => 'Ready to Create Something Special?',
            'personalised_cta_sub'      => 'Send us a message and we will get started on your personalised hamper.',
            'personalised_cta_email_subject' => 'Customised Order Enquiry',
        ];
        foreach ($personalisedContent as $key => $value) {
            SiteContent::updateOrCreate(['key' => $key], ['value' => $value, 'content_type' => 'text']);
        }

        // ── Create Your Own page ──────────────────────────────────────────
        $cyoContent = [
            'cyo_hero_label'   => 'Create Your Own',
            'cyo_hero_heading' => 'Build Your Perfect Hamper',
            'cyo_hero_sub'     => 'Pick what goes in, and we will put it together beautifully.',
            'cyo_step1_title'  => 'Choose Your Items',
            'cyo_step1_body'   => 'Browse our selection and tell us what you would like included. Mix and match freely.',
            'cyo_step2_title'  => 'Set Your Budget',
            'cyo_step2_body'   => 'Let us know your budget and we will make sure every penny counts.',
            'cyo_step3_title'  => 'Add a Personal Touch',
            'cyo_step3_body'   => 'Include a card message, a theme, or any special packaging requests.',
            'cyo_step4_title'  => 'We Assemble and Deliver',
            'cyo_step4_body'   => 'We put it all together and deliver it straight to your recipient.',
            'cyo_cta_title'    => 'Ready to Create Something Special?',
            'cyo_cta_sub'      => 'Send us a message and we will get started on your personalised hamper.',
            'cyo_cta_email_subject' => 'Create Your Own Hamper Enquiry',
        ];
        foreach ($cyoContent as $key => $value) {
            SiteContent::updateOrCreate(['key' => $key], ['value' => $value, 'content_type' => 'text']);
        }

        // ── Corporate Orders page ─────────────────────────────────────────
        $corporateContent = [
            'corporate_hero_label'   => 'Corporate Orders',
            'corporate_hero_heading' => 'Gifting at Scale, Done Right',
            'corporate_hero_sub'     => 'Impress clients, reward your team, and make your brand memorable with Aura Gifts.',
            'corporate_card1_title'  => 'Bulk Orders Welcome',
            'corporate_card1_body'   => 'Whether it is 10 or 500 hampers, we handle volume orders with the same care and attention to detail.',
            'corporate_card2_title'  => 'Custom Branding Available',
            'corporate_card2_body'   => 'Add your company logo, branded ribbon, or custom packaging to make every hamper uniquely yours.',
            'corporate_card3_title'  => 'Reliable and On Time',
            'corporate_card3_body'   => 'We plan ahead to make sure your corporate gifts arrive on time, every time. No stress, no delays.',
            'corporate_stat1_value'  => '500+',
            'corporate_stat1_label'  => 'Hampers Delivered',
            'corporate_stat2_value'  => '100%',
            'corporate_stat2_label'  => 'Satisfaction Focused',
            'corporate_stat3_value'  => '24hr',
            'corporate_stat3_label'  => 'Response Time',
            'corporate_cta_title'    => 'Ready to Create Something Special?',
            'corporate_cta_sub'      => 'Send us a message and we will get started on your corporate order.',
            'corporate_cta_email_subject' => 'Corporate Order Enquiry',
        ];
        foreach ($corporateContent as $key => $value) {
            SiteContent::updateOrCreate(['key' => $key], ['value' => $value, 'content_type' => 'text']);
        }

        echo "Content seeded successfully!\n";

        // ── Banners ───────────────────────────────────────────────────────
        if (\App\Models\Banner::count() === 0) {
            \App\Models\Banner::create([
                'eyebrow'    => 'Luxury · Thoughtful · Personal',
                'title'      => 'The Art of Refined Gifting',
                'subtitle'   => 'Beautifully curated hampers delivered across the Maldives.',
                'image_url'  => 'img/banners/banner1.jpg',
                'sort_order' => 0,
                'is_active'  => true,
            ]);
            \App\Models\Banner::create([
                'eyebrow'    => 'Every Gift Tells a Story',
                'title'      => 'Every Gift Tells a Story',
                'subtitle'   => 'From birthdays to corporate orders, we make every occasion memorable.',
                'image_url'  => 'img/banners/banner2.avif',
                'sort_order' => 1,
                'is_active'  => true,
            ]);
            echo "Banners seeded!\n";
        }

        // ── Products ──────────────────────────────────────────────────────
        if (\App\Models\Product::count() === 0) {
            $products = [
                ['name' => 'The Classic Hamper',       'description' => 'A timeless selection of premium treats, beautifully presented for any occasion.', 'price_mvr' => 450,  'stock' => 10, 'badge' => 'Popular',  'image_url' => 'img/hampers/hamper1.jpeg', 'sort_order' => 0, 'featured' => true],
                ['name' => 'The Luxury Gift Set',      'description' => 'An indulgent collection of the finest items, crafted for those who deserve the best.', 'price_mvr' => 850,  'stock' => 8,  'badge' => 'Premium',  'image_url' => 'img/hampers/hamper2.jpeg', 'sort_order' => 1, 'featured' => true],
                ['name' => 'The Sweet Treat Box',      'description' => 'A delightful assortment of handpicked sweets and confections, perfect for celebrations.', 'price_mvr' => 350,  'stock' => 15, 'badge' => null,       'image_url' => 'img/hampers/hamper3.jpeg', 'sort_order' => 2, 'featured' => true],
                ['name' => 'The Wellness Hamper',      'description' => 'A thoughtful blend of wellness essentials curated to nurture and refresh.', 'price_mvr' => 650,  'stock' => 6,  'badge' => 'New',      'image_url' => 'img/hampers/hamper4.jpeg', 'sort_order' => 3, 'featured' => true],
                ['name' => 'The Corporate Gift Box',   'description' => 'A sophisticated hamper ideal for clients, colleagues, and business milestones.', 'price_mvr' => 950,  'stock' => 20, 'badge' => 'Corporate','image_url' => 'img/hampers/hamper5.jpeg', 'sort_order' => 4, 'featured' => true],
                ['name' => 'The Celebration Hamper',   'description' => 'Everything you need to make a celebration truly unforgettable.', 'price_mvr' => 750,  'stock' => 12, 'badge' => null,       'image_url' => 'img/hampers/hamper6.jpeg', 'sort_order' => 5, 'featured' => true],
            ];
            foreach ($products as $p) {
                \App\Models\Product::create([
                    'name'        => $p['name'],
                    'description' => $p['description'],
                    'price_mvr'   => $p['price_mvr'],
                    'stock'       => $p['stock'],
                    'badge'       => $p['badge'],
                    'image_url'   => $p['image_url'],
                    'sort_order'  => $p['sort_order'],
                    'featured'    => $p['featured'],
                    'is_active'   => true,
                ]);
            }
            echo "Products seeded!\n";
        }
    }
}
