"""Generate WordPress.org icon + banner assets from the CitationRate logo source.

The source logo is only 120x120, so every WP.org variant is an upscale. After
Lanczos resampling we apply an UnsharpMask pass to recover edge sharpness on
the white quotation marks against the sage-green background.

Outputs:
  icon-256x256.png
  icon-128x128.png
  banner-772x250.png    (standard banner)
  banner-1544x500.png   (retina banner)
"""

import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

HERE = os.path.dirname(__file__)
LOGO_PATH = os.path.join(HERE, "icon-source.png")

src = Image.open(LOGO_PATH).convert("RGB")
BG = src.getpixel((10, 10))


def find_font(size: int) -> ImageFont.FreeTypeFont:
    candidates = [
        "/System/Library/Fonts/HelveticaNeue.ttc",
        "/System/Library/Fonts/Helvetica.ttc",
        "/Library/Fonts/Arial.ttf",
        "/System/Library/Fonts/Supplemental/Arial.ttf",
    ]
    for path in candidates:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, size)
            except Exception:
                pass
    return ImageFont.load_default()


def sharpen(img: Image.Image) -> Image.Image:
    """Two-pass sharpening: gentle unsharp mask then a fine pass to crisp edges."""
    out = img.filter(ImageFilter.UnsharpMask(radius=2.0, percent=180, threshold=2))
    out = out.filter(ImageFilter.UnsharpMask(radius=0.8, percent=80, threshold=0))
    return out


def upscaled_logo(target_size: int) -> Image.Image:
    """Stepwise upscale from 120 to target with Lanczos + sharpen at each step."""
    img = src
    current = img.size[0]
    while current < target_size:
        next_size = min(current * 2, target_size)
        img = img.resize((next_size, next_size), Image.LANCZOS)
        img = sharpen(img)
        current = next_size
    if img.size[0] != target_size:
        img = img.resize((target_size, target_size), Image.LANCZOS)
        img = sharpen(img)
    return img


def make_icon(size: int, output: str) -> None:
    img = upscaled_logo(size)
    img.save(output, "PNG", optimize=True)
    print(f"wrote {output}  ({size}x{size})")


def draw_banner(width: int, height: int, output: str) -> None:
    img = Image.new("RGB", (width, height), BG)
    draw = ImageDraw.Draw(img)

    logo_size = int(height * 0.7)
    logo = upscaled_logo(logo_size)
    logo_x = width - logo_size - int(width * 0.06)
    logo_y = (height - logo_size) // 2
    img.paste(logo, (logo_x, logo_y))

    title_size = int(height * 0.22)
    subtitle_size = int(height * 0.09)
    tag_size = int(height * 0.07)

    title_font = find_font(title_size)
    subtitle_font = find_font(subtitle_size)
    tag_font = find_font(tag_size)

    pad = int(width * 0.05)
    text_y = int(height * 0.30)

    draw.text((pad, text_y), "Citability Score", fill=(255, 255, 255), font=title_font)
    text_y += int(title_size * 1.15)
    draw.text((pad, text_y), "Ottimizza i contenuti per gli AI search engine", fill=(245, 245, 245), font=subtitle_font)
    text_y += int(subtitle_size * 1.5)
    draw.text((pad, text_y), "by CitationRate", fill=(220, 230, 225), font=tag_font)

    img.save(output, "PNG", optimize=True)
    print(f"wrote {output}  ({width}x{height})")


make_icon(256, os.path.join(HERE, "icon-256x256.png"))
make_icon(128, os.path.join(HERE, "icon-128x128.png"))
draw_banner(772, 250, os.path.join(HERE, "banner-772x250.png"))
draw_banner(1544, 500, os.path.join(HERE, "banner-1544x500.png"))
