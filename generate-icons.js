import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import sharp from 'sharp';

// Get the directory name
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Sizes needed for PWA icons
const sizes = [72, 96, 128, 144, 152, 192, 384, 512];

async function generateIcons() {
  try {
    // Read the SVG file
    const svgPath = path.join(__dirname, 'public', 'icons', 'icon-base.svg');
    const svgContent = fs.readFileSync(svgPath, 'utf8');
    
    // Generate PNG for each size
    for (const size of sizes) {
      console.log(`Generating icon-${size}x${size}.png`);
      
      // Use Sharp to convert SVG to PNG
      await sharp(Buffer.from(svgContent))
        .resize(size, size)
        .toFile(path.join(__dirname, 'public', 'icons', `icon-${size}x${size}.png`));
    }
    
    console.log('All icons generated successfully!');
  } catch (error) {
    console.error('Error generating icons:', error);
  }
}

generateIcons(); 