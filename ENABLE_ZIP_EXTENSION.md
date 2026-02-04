# Enable ZIP Extension in XAMPP

The folder download feature requires PHP's ZIP extension. Follow these steps to enable it:

## Steps to Enable ZIP Extension:

1. **Open php.ini file:**
   - Go to `C:\xampp\php\php.ini`
   - Or click on XAMPP Control Panel → Apache → Config → php.ini

2. **Find the zip extension line:**
   - Press `Ctrl+F` and search for: `;extension=zip`

3. **Uncomment the line:**
   - Remove the semicolon (`;`) at the beginning of the line
   - Change from: `;extension=zip`
   - Change to: `extension=zip`

4. **Save the file**

5. **Restart Apache:**
   - In XAMPP Control Panel, click "Stop" on Apache
   - Then click "Start" again

6. **Verify it's working:**
   - Try downloading a folder again
   - The ZIP extension should now work!

## If the line doesn't exist:

Add this line in the extensions section:
```
extension=zip
```

## Alternative: Check if extension file exists

Make sure the file `php_zip.dll` exists in:
- `C:\xampp\php\ext\php_zip.dll`

If it doesn't exist, you may need to reinstall XAMPP or download the extension separately.
