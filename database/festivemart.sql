-- ============================================================
-- FestiVmart Database Schema
-- Run this file to set up the complete database
-- ============================================================

CREATE DATABASE IF NOT EXISTS festivemart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE festivemart;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reviews, order_items, cart, orders, coupons, products, categories, addresses, users, festivals;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FESTIVALS
-- ============================================================
CREATE TABLE festivals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    theme_color VARCHAR(7) DEFAULT '#FF6B35',
    accent_color VARCHAR(7) DEFAULT '#FFD700',
    description TEXT,
    banner_tagline VARCHAR(255),
    particle_type ENUM('confetti','petals','leaves','diyo','snowflakes','stars') DEFAULT 'confetti',
    is_featured TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    icon VARCHAR(10) DEFAULT '🎁',
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE CASCADE
);

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','customer') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- ADDRESSES
-- ============================================================
CREATE TABLE addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address_line VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) DEFAULT 'Bagmati',
    is_default TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    festival_id INT,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    image VARCHAR(255) DEFAULT '',
    variations TEXT COMMENT 'JSON: sizes, colors etc.',
    is_featured TINYINT(1) DEFAULT 0,
    is_preorder TINYINT(1) DEFAULT 0,
    discount_percent DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ============================================================
-- COUPONS
-- ============================================================
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    discount_percent DECIMAL(5,2) NOT NULL,
    festival_id INT,
    min_order DECIMAL(10,2) DEFAULT 0.00,
    expiry_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (festival_id) REFERENCES festivals(id) ON DELETE SET NULL
);

-- ============================================================
-- ORDERS
-- ============================================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coupon_id INT,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping DECIMAL(10,2) DEFAULT 100.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    address_id INT,
    delivery_address TEXT,
    status ENUM('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending',
    payment_method VARCHAR(50) DEFAULT 'Cash on Delivery',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
);

-- ============================================================
-- ORDER ITEMS
-- ============================================================
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================================
-- CART
-- ============================================================
CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    UNIQUE KEY unique_cart_item (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================================
-- REVIEWS
-- ============================================================
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY one_review_per_product (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================================
-- KHALTI TRANSACTIONS
-- ============================================================
CREATE TABLE khalti_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    pidx VARCHAR(100) NOT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'Initiated',
    response_data TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ============================================================
-- SEED: FESTIVALS (2026-2027 calendar)
-- ============================================================
INSERT INTO festivals (name, slug, start_date, end_date, theme_color, accent_color, description, banner_tagline, particle_type, is_featured) VALUES
('Holi',              'holi',             '2026-03-02','2026-03-06','#FF4081','#FF9100', 'Festival of Colors — celebrate with vibrant gulal and water fun!',         ' 🎨 Celebrate with Colors! & Happy Holi',              'confetti',   1),
('Nepali New Year',   'new-year',         '2026-04-12','2026-04-18','#2ECC71','#FFD700', 'Ring in 2083 BS with gifts, calendars, and festive cheer!',                 'Happy New Year 2083! नव वर्षको शुभकामना 🎊',        'stars',      0),
('Buddha Jayanti',    'buddha-jayanti',   '2026-05-10','2026-05-12','#F39C12','#FFFDE7', 'Celebrate the birth of Lord Buddha with peace and grace.',                  'Peace & Enlightenment 🌸 Happy Buddha Jayanti!',     'petals',     0),
('Teej',              'teej',             '2026-08-23','2026-08-26','#E91E63','#FF4081', 'Sacred festival for women — celebrate with sarees, bangles & mehendi!',    'Shubha Haritalika Teej! 🌸 सुभ तीज!',              'petals',     0),
('Indra Jatra',       'indra-jatra',      '2026-09-08','2026-09-14','#7B1FA2','#E91E63', 'Kathmandu valley\'s grand chariot festival of Lord Indra.',                 'Celebrate Indra Jatra — Yenya Punhi 🎭!',            'stars',      0),
('Dashain',           'dashain',          '2026-10-05','2026-10-20','#FF6B35','#FFD700', 'The biggest festival of Nepal — 15 days of Goddess Durga worship!',        'Shubha Dashain! विजयादशमी को शुभकामना 🙏',          'leaves',     0),
('Tihar',             'tihar',            '2026-10-24','2026-10-28','#9C27B0','#FFD700', 'Festival of Lights — illuminate homes with diyos and rangoli!',             'Happy Tihar! दीपावलीको शुभकामना 🪔',                'diyo',       0),
('Chhath',            'chhath',           '2026-10-31','2026-11-03','#FF8C00','#FFD700', 'Sacred sun worship festival — celebrate with fresh fruits and prayers.',    'Shubha Chhath Parwa! 🌅 Jay Chhathi Maiya!',         'stars',      0),
('Yomari Punhi',      'yomari-punhi',     '2026-12-05','2026-12-06','#8D6E63','#FFCC80', 'Newar festival celebrating harvest with yomari sweet dumplings.',           'Celebrate Yomari Punhi with sweet traditions! 🍡',   'stars',      0),
('Maghe Sankranti',   'maghe-sankranti',  '2027-01-14','2027-01-16','#FF5722','#FF9800', 'Marks the start of Magh — celebrated with til laddu and chaku!',            'Shubha Maghe Sankranti! माघे संक्रान्तिको शुभकामना 🌕','snowflakes', 0),
('Basanta Panchami',  'basanta-panchami', '2027-01-22','2027-01-23','#FFEB3B','#FF9800', 'Festival of spring and knowledge — celebrate Saraswati Puja!',              'Happy Basanta Panchami! 🌼 Saraswati Ko Jai!',       'petals',     0),
('Shivaratri',        'shivaratri',       '2027-02-17','2027-02-18','#6A1B9A','#CE93D8', 'Great night of Lord Shiva — celebrate with devotion and prayers.',          'Shubha Maha Shivaratri! ॐ नमः शिवाय 🕉️',           'stars',      0);

-- ============================================================
-- SEED: CATEGORIES
-- ============================================================
INSERT INTO categories (festival_id, name, slug, icon) VALUES
-- Holi (1)
(1, 'Colors & Gulal',        'colors-gulal',       '🎨'),
(1, 'Water Fun',             'water-fun',          '💦'),
(1, 'Party Essentials',      'party-essentials',   '🎉'),
-- New Year (2)
(2, 'Gift Hampers',          'gift-hampers',       '🎁'),
(2, 'Calendars & Cards',     'calendars-cards',    '📅'),
(2, 'Personalized Gifts',    'personalized-gifts', '✨'),
-- Buddha Jayanti (3)
(3, 'Prayer Items',          'prayer-items',       '🪔'),
(3, 'Peace Gifts',           'peace-gifts',        '☮️'),
-- Teej (4)
(4, 'Traditional Sarees',    'traditional-sarees', '👘'),
(4, 'Bangles & Jewelry',     'bangles-jewelry',    '💍'),
(4, 'Mehendi & Beauty',      'mehendi-beauty',     '🌿'),
-- Indra Jatra (5)
(5, 'Traditional Wear',      'traditional-wear-ij','👔'),
(5, 'Festival Decor',        'festival-decor-ij',  '🎭'),
-- Dashain (6)
(6, 'Puja & Tika Items',     'puja-tika',          '🙏'),
(6, 'Traditional Clothes',   'traditional-clothes','👗'),
(6, 'Food & Pre-orders',     'food-preorders',     '🍖'),
(6, 'Home Decorations',      'home-decorations',   '🌼'),
(6, 'Gift Hampers',          'gift-hampers-d',     '🎁'),
-- Tihar (7)
(7, 'Diyos & Lights',        'diyos-lights',       '🪔'),
(7, 'Rangoli & Colors',      'rangoli-colors',     '🎨'),
(7, 'Bhai Tika Sets',        'bhai-tika',          '❤️'),
(7, 'Flower Garlands',       'flower-garlands',    '🌸'),
-- Chhath (8)
(8, 'Puja Thali & Items',    'puja-thali',         '☀️'),
(8, 'Fresh Fruits Basket',   'fruits-basket',      '🍎'),
-- Maghe Sankranti (10)
(10,'Traditional Sweets',    'traditional-sweets', '🍬'),
(10,'Yam & Ghee',            'yam-ghee',           '🥔');

-- ============================================================
-- SEED: ADMIN USER (password: Admin@123)
-- ============================================================
INSERT INTO users (full_name, username, email, phone, password, role) VALUES
('ADMIN USER', 'admin', 'admin@festivemart.com', '+9779800000000',
 '$2y$10$TKh8H1.PyfcAl0SnHJFOWuJFPCNE3jZPGkv/LiCJHHVbLPzqKiBli', 'admin');

-- ============================================================
-- SEED: PRODUCTS
-- ============================================================
INSERT INTO products (festival_id, category_id, name, description, price, stock, is_featured, discount_percent, is_preorder) VALUES
-- Holi Products
(1,1,'Premium Gulal Set (5 Colors)','Organic, skin-safe gulal in 5 vibrant festival colors. Perfect for all age groups. Eco-friendly formula, easy to wash.',299.00,150,1,10.00,0),
(1,1,'Herbal Color Pack (8 Colors)','100% natural herbal colors safe for children and adults. Made from pure botanical extracts. Set of 8 shades.',450.00,80,0,0.00,0),
(1,2,'Water Balloon Pack (500pcs)','Premium latex water balloons, fills in seconds. Tear-resistant yet easy to burst. Bulk festival pack.',199.00,200,1,0.00,0),
(1,2,'Pichkari Water Gun XL','Powerful 1.5L capacity water gun. Extended range up to 8 meters. Ergonomic design for easy use.',599.00,60,0,15.00,0),
(1,3,'Holi White T-Shirt','Premium 100% cotton white t-shirt — the perfect Holi canvas. Available in S/M/L/XL.',349.00,120,0,0.00,0),
(1,3,'Complete Holi Party Pack','All-in-one Holi kit: 5-color gulal, 200 water balloons, pichkari, and UV safety glasses. Great value!',999.00,40,1,20.00,0),
-- Nepali New Year Products
(2,4,'Premium Gift Hamper 2083','Curated New Year hamper: assorted sweets, dry fruits, herbal tea, and festive items in a premium box.',1299.00,50,1,0.00,0),
(2,5,'Nepali Calendar 2083','Full-color A3 wall calendar with all festivals, public holidays, and tithi marked. Traditional motif border.',149.00,200,0,0.00,0),
(2,5,'Custom Greeting Card','Premium quality New Year greeting card. Personalize with your name and message. Envelope included.',99.00,300,0,0.00,0),
(2,6,'Personalized Gift Box','Custom-engraved wooden gift box with festive ribbon. Fill with your choice of goodies. Lead time: 2 days.',799.00,30,1,0.00,0),
-- Teej Products
(4,9,'Red Silk Saree (Teej Special)','Traditional red silk saree with golden Dhaka border. Lightweight, elegant drape. Perfect for Teej puja.',2499.00,40,1,0.00,0),
(4,10,'Green Bangle Set (24pcs)','Hand-crafted glass bangles in 8 shades of green. Traditional and auspicious for Teej.',299.00,100,0,0.00,0),
(4,10,'Gold-Plated Pote Necklace','Traditional red-gold pote necklace for married women. Authentic Newari craftsmanship. Anti-tarnish coating.',899.00,60,1,10.00,0),
(4,11,'Mehendi Cone Pack (6pcs)','Natural henna cones with rich dark stain. Includes free design booklet with 20 traditional Teej patterns.',199.00,150,0,0.00,0),
(4,10,'Bridal Jewelry Set (Teej)','Complete set: necklace, earrings, tikka, bangles, and payal. Gold-plated with premium stones.',3999.00,20,1,5.00,0),
-- Dashain Products
(6,14,'Premium Tika Set','Complete tika kit: red tika powder, dahi (curd) container, jamara seeds, and blessing incense sticks.',399.00,200,1,0.00,0),
(6,14,'Jamara Growing Kit 10-Day','All-in-one jamara starter: tray, organic soil, barley seeds, and care guide. Fresh yellow jamara for Dashami.',249.00,100,0,0.00,0),
(6,15,'Daura Suruwal (Traditional)','Authentic Nepali Daura Suruwal in premium cotton-silk blend. Available sizes: S/M/L/XL/XXL. Handstitched.',3499.00,30,1,0.00,0),
(6,15,'Gunyu Cholo Set (Women)','Traditional Gunyu Cholo in festive red and gold. Includes matching fariya. Machine washable silk blend.',2999.00,25,0,0.00,0),
(6,16,'Fresh Goat Meat Pre-order 5kg','Pre-book fresh goat meat for Dashami. Sourced locally, hygienically processed. Delivered on Dashami morning.',3500.00,50,1,0.00,1),
(6,17,'Festival Home Decoration Set','Marigold garlands, rangoli colors, door torana, auspicious stickers, and LED fairy lights combo.',599.00,80,0,15.00,0),
(6,18,'Dashain Gift Hamper (Deluxe)','Premium hamper: sel roti mix, sweets, dry fruits, puja items, and festive packaging. Corporate gifting ready.',1499.00,40,1,0.00,0),
-- Tihar Products
(7,19,'Handmade Clay Diyo Set (50pcs)','Traditional handcrafted clay oil lamps. Smooth finish, wide base for stability. Long-burning wick included.',299.00,300,1,0.00,0),
(7,19,'LED Fairy Lights 10m (Warm White)','Premium copper wire LED lights, energy-efficient, flexible. Waterproof for outdoor use. 8 lighting modes.',499.00,150,0,10.00,0),
(7,19,'LED Color Diyo Set (Electric)','Modern plug-in LED diyos in amber flame effect. Zero risk, flameless design for safe decoration.',799.00,80,1,0.00,0),
(7,20,'Rangoli Color Set (10 Colors)','Vibrant rangoli powder in 10 shades. Easy-flow nozzle, fade-resistant. 200g per color.',249.00,200,0,0.00,0),
(7,21,'Complete Bhai Tika Gift Set','Everything for Bhai Tika: five-color tika, flower garland, sel roti mix, puja thali, and a gift voucher.',799.00,60,1,0.00,0),
(7,22,'Makhamali Mala (Flower Garland)','Fresh marigold and makhamali garland for doors and puja. Available in 3ft, 5ft, and 10ft lengths.',199.00,100,1,0.00,0),
-- Maghe Sankranti Products
(10,25,'Til Ko Laddu (500g)','Traditional sesame-jaggery sweet balls from Bhaktapur. Made fresh for Maghe Sankranti. Preservative-free.',299.00,100,1,0.00,0),
(10,25,'Chaku (500g)','Pure sugarcane chaku — Maghe Sankranti specialty from Thimi. Natural sweetener, no additives.',199.00,120,0,0.00,0),
(10,26,'Pure Cow Ghee (1L)','Organic A2 cow ghee from traditional bilona method. Rich aroma, golden color. Festival essential.',1499.00,80,1,0.00,0),
(10,26,'Yam (Tarul) Pack (3kg)','Fresh yam from hill farms — a Maghe Sankranti must-have. Organic, naturally grown.',199.00,60,0,0.00,0),
(10,25,'Maghe Sankranti Hamper','Complete festive box: til laddu, chaku, ghee, yam, and khichdi mix. Traditional celebration set.',999.00,40,1,5.00,0);

-- ============================================================
-- SEED: COUPONS
-- ============================================================
INSERT INTO coupons (code, discount_percent, festival_id, min_order, expiry_date, is_active) VALUES
('HOLI2026',     15.00, 1,  500.00, '2026-03-10', 1),
('NEWYEAR2083',  10.00, 2,  300.00, '2026-04-20', 1),
('TEEJ2026',     12.00, 4,  800.00, '2026-08-30', 1),
('DASHAIN25',    25.00, 6, 1000.00, '2026-10-25', 1),
('TIHAR20',      20.00, 7,  500.00, '2026-11-02', 1),
('MAGHE15',      15.00, 10, 400.00, '2027-01-18', 1),
('FESTIVE10',    10.00, NULL,200.00, '2027-12-31', 1),
('WELCOME5',      5.00, NULL,100.00, '2027-12-31', 1);