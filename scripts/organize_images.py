import os, glob, re, json
from PIL import Image

BASE_DIR = r'e:\AI_projects\dataset\ds\datasheet-generator-php'
SRC_DIR = os.path.join(BASE_DIR, 'source_datasheets_md')
APP_IMG = os.path.join(BASE_DIR, 'app', 'images')
DATA_FILE = os.path.join(BASE_DIR, 'data', 'fixtures_app_data.json')
TMPL_DIR = os.path.join(APP_IMG, 'template')

os.makedirs(TMPL_DIR, exist_ok=True)

# Standard SC components as defaults
DEFAULT_CHIP = 'template/chip.png'
DEFAULT_DRIVER = 'template/driver.png'
DEFAULT_DRIVER_BODY = 'template/driver_body.png'

with open(DATA_FILE, 'r', encoding='utf-8') as f:
    app_data = json.load(f)

def get_image_info(cat, img_name):
    # Try exact category folder first
    p = os.path.join(APP_IMG, cat, img_name)
    if not os.path.exists(p):
        matches = glob.glob(os.path.join(APP_IMG, '**', img_name), recursive=True)
        if matches:
            p = matches[0]
        else:
            return None, (0, 0), None
    try:
        im = Image.open(p)
        return p, im.size, im.mode
    except:
        return None, (0, 0), None

def create_composite_heatsink_if_needed(cat, valid_imgs, prefix):
    """If there are 3 distinct views (front, back, side), composite them like Screenshot 4."""
    if len(valid_imgs) >= 3:
        # Check if we can identify side, back, front
        # Usually valid_imgs[-3] is side, -2 is back, -1 is front or similar
        comp_filename = f"{prefix}_fig1_composite.png"
        comp_path = os.path.join(APP_IMG, cat, comp_filename)
        if os.path.exists(comp_path):
            return f"{cat}/{comp_filename}"
        
        try:
            imgs = [Image.open(os.path.join(APP_IMG, cat, img)) for img in valid_imgs[-3:]]
            # sort by aspect ratio or size
            # side view is usually the tallest or narrowest
            tallest = max(imgs, key=lambda im: im.height / max(1, im.width))
            others = [im for im in imgs if im != tallest]
            if len(others) == 2:
                top_im, bottom_im = others[0], others[1]
                max_w = max(top_im.width, bottom_im.width)
                tot_h = top_im.height + bottom_im.height + 15
                tot_w = max_w + tallest.width + 15
                out_h = max(tot_h, tallest.height)

                canvas = Image.new('RGBA', (tot_w, out_h), (255, 255, 255, 0))
                canvas.paste(top_im, (int((max_w - top_im.width) / 2), 0), top_im if top_im.mode == 'RGBA' else None)
                canvas.paste(bottom_im, (int((max_w - bottom_im.width) / 2), top_im.height + 15), bottom_im if bottom_im.mode == 'RGBA' else None)
                canvas.paste(tallest, (max_w + 15, int((out_h - tallest.height) / 2)), tallest if tallest.mode == 'RGBA' else None)
                canvas.save(comp_path)
                return f"{cat}/{comp_filename}"
        except Exception as e:
            print(f"Error creating composite for {prefix}: {e}")

    # Fallback to the last valid heatsink image
    return f"{cat}/{valid_imgs[-1]}"

def process_variant(v, cat):
    sf = v.get('source_file', '')
    md_path = os.path.join(SRC_DIR, sf)
    prefix = os.path.splitext(os.path.basename(sf))[0]
    
    if not os.path.exists(md_path):
        return {
            'hero': None,
            'heatsink': None,
            'chip': DEFAULT_CHIP,
            'driver': DEFAULT_DRIVER,
            'driver_body': DEFAULT_DRIVER_BODY
        }
    
    content = open(md_path, encoding='utf-8').read()
    pages = re.split(r'##\s*Page\s*(\d+)', content)
    page_map = {}
    if len(pages) > 1:
        for p_num, p_text in zip(pages[1::2], pages[2::2]):
            imgs = re.findall(r'!\[extracted image\]\(images/([^\)]+)\)', p_text)
            page_map[int(p_num)] = (p_text, imgs)

    hero = None
    heatsink = None
    chip = None
    driver = None
    driver_body = None

    # 1. HERO (Page 1)
    if 1 in page_map:
        _, p1_imgs = page_map[1]
        candidates = []
        for img_name in p1_imgs:
            p, size, mode = get_image_info(cat, img_name)
            if not p: continue
            w, h = size
            if (w, h) in [(640, 640), (429, 429), (467, 467)]: continue
            if w <= 10 or h <= 10: continue
            if (w, h) in [(769, 819)]: continue # Orange divider line
            if (w, h) in [(197, 123), (259, 131), (256, 128)]: continue # Logo
            candidates.append((w * h, img_name))
        if candidates:
            candidates.sort(key=lambda x: x[0], reverse=True)
            hero = f"{cat}/{candidates[0][1]}"
        elif p1_imgs:
            hero = f"{cat}/{p1_imgs[-1]}"

    # 2. HEATSINK / BODY (Figure 1 or Body and Heat-Sink section)
    m = re.search(r'Figure\s*1\s*:\s*Body\s*and\s*Heat-Sink.*?(?=##\s*Page|Figure|\Z)', content, re.DOTALL | re.IGNORECASE)
    valid_hs = []
    if m:
        imgs = re.findall(r'!\[extracted image\]\(images/([^\)]+)\)', m.group(0))
        for img_name in imgs:
            p, size, _ = get_image_info(cat, img_name)
            if not p: continue
            w, h = size
            if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
            if w <= 10 or h <= 10: continue
            valid_hs.append(img_name)
    if not valid_hs:
        for p_num, (p_text, imgs) in page_map.items():
            if 'body and heat-sink' in p_text.lower():
                for img_name in imgs:
                    p, size, _ = get_image_info(cat, img_name)
                    if not p: continue
                    w, h = size
                    if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
                    if w <= 10 or h <= 10: continue
                    valid_hs.append(img_name)
                if valid_hs: break

    if valid_hs:
        heatsink = create_composite_heatsink_if_needed(cat, valid_hs, prefix)
    elif hero:
        heatsink = hero

    # 3. CHIP (Figure 2: Philips Lumiled 3030)
    m = re.search(r'Figure\s*2\s*:\s*Philips\s*Lumiled.*?(?=##\s*Page|Figure|\Z)', content, re.DOTALL | re.IGNORECASE)
    valid_chips = []
    if m:
        imgs = re.findall(r'!\[extracted image\]\(images/([^\)]+)\)', m.group(0))
        for img_name in imgs:
            p, size, _ = get_image_info(cat, img_name)
            if not p: continue
            w, h = size
            if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
            valid_chips.append(img_name)
    if not valid_chips:
        for p_num, (p_text, imgs) in page_map.items():
            if 'philips lumiled' in p_text.lower() or 'figure2' in p_text.lower():
                for img_name in imgs:
                    p, size, _ = get_image_info(cat, img_name)
                    if not p: continue
                    w, h = size
                    if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
                    valid_chips.append(img_name)
                if valid_chips: break
    if valid_chips:
        chip = f"{cat}/{valid_chips[0]}"
    else:
        chip = DEFAULT_CHIP

    # 4. DRIVER (Figure 6: Short Circuit Driver or Philips driver)
    m = re.search(r'Figure\s*6\s*:\s*Short\s*Circuit\s*Driver.*?(?=##\s*Page|Figure|\Z)', content, re.DOTALL | re.IGNORECASE)
    valid_drivers = []
    if m:
        imgs = re.findall(r'!\[extracted image\]\(images/([^\)]+)\)', m.group(0))
        for img_name in imgs:
            p, size, _ = get_image_info(cat, img_name)
            if not p: continue
            w, h = size
            if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
            valid_drivers.append(img_name)
    if not valid_drivers:
        for p_num, (p_text, imgs) in page_map.items():
            if 'universal isolated led driver' in p_text.lower() or 'figure6' in p_text.lower() or 'xitanium' in p_text.lower():
                for img_name in imgs:
                    p, size, _ = get_image_info(cat, img_name)
                    if not p: continue
                    w, h = size
                    if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
                    if w in [514, 523, 541, 520]: continue # polar curve
                    valid_drivers.append(img_name)
                if valid_drivers: break
    if valid_drivers:
        driver = f"{cat}/{valid_drivers[-1]}"
    else:
        driver = DEFAULT_DRIVER

    # 5. DRIVER BODY / MECHANICAL DIMS (Figure 7)
    m = re.search(r'Figure\s*7\s*:\s*Driver\s*mechanical\s*dimensions.*?(?=##\s*Page|Figure|\Z)', content, re.DOTALL | re.IGNORECASE)
    valid_db = []
    if m:
        imgs = re.findall(r'!\[extracted image\]\(images/([^\)]+)\)', m.group(0))
        for img_name in imgs:
            p, size, _ = get_image_info(cat, img_name)
            if not p: continue
            w, h = size
            if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
            valid_db.append(img_name)
    if not valid_db:
        for p_num, (p_text, imgs) in page_map.items():
            if 'mechanical dimensions' in p_text.lower() or 'driver mechanical' in p_text.lower():
                for img_name in imgs:
                    p, size, _ = get_image_info(cat, img_name)
                    if not p: continue
                    w, h = size
                    if (w, h) in [(640, 640), (429, 429), (197, 123), (259, 131)]: continue
                    valid_db.append(img_name)
                if valid_db: break
    if valid_db:
        driver_body = f"{cat}/{valid_db[-1]}"
    else:
        driver_body = DEFAULT_DRIVER_BODY

    return {
        'hero': hero,
        'heatsink': heatsink,
        'chip': chip,
        'driver': driver,
        'driver_body': driver_body
    }

print("Processing all fixtures and variants...")
total_v = 0
for fix in app_data['fixtures']:
    cat = fix.get('cat', '')
    for v in fix.get('v', []):
        total_v += 1
        img_dict = process_variant(v, cat)
        v['images'] = img_dict

        # Also update figs list with image_path
        if 'figs' in v:
            for fig in v['figs']:
                fno = str(fig.get('figure_no', ''))
                if fno == '1' and img_dict['heatsink']:
                    fig['image_path'] = img_dict['heatsink']
                elif fno == '2' and img_dict['chip']:
                    fig['image_path'] = img_dict['chip']
                elif fno == '6' and img_dict['driver']:
                    fig['image_path'] = img_dict['driver']
                elif fno == '7' and img_dict['driver_body']:
                    fig['image_path'] = img_dict['driver_body']

with open(DATA_FILE, 'w', encoding='utf-8') as f:
    json.dump(app_data, f, indent=2, ensure_ascii=False)

print(f"Successfully processed {total_v} variants and updated {DATA_FILE}")
