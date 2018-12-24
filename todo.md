## General

- [ ] create phar package
- [ ] tool for monitoring changes on critical system files, if something will change it will inform
- [ ] allow to retrieve changed critical files + show diff
- [ ] add config reader from `/etc/tools-cli` (src/Console/Commands.php:46)
- [ ] create bootstrap function (src/Console/Commands.php:47)
- [ ] implement Log & Event functions (src/Console/Commands.php:56)
- [ ] implement Log & Event functions (src/Console/Commands.php:56)
- [ ] set default command (src/Console/Commands.php:70)
- [ ] read tools commands from vendor (+global vendor) (recognize by special namespace) (src/Console/Commands.php:76)
- [ ] remove to general Exceptions

## Fs

### CopyAndReplaceExists
- [x] copy files from one dir to another, if file with the same name exist compare, if different make name from hash, if exists replace
- [ ] break when source have dirs (choose correct path for that case) `Fs::copy`
- [ ] code clean up

### NameToDate
- [ ] implement https://github.com/hollodotme/fast-cgi-client (src/Tools/Fs/NameToDateTool.php:4)
- [ ] check that source & destination dir exists (src/Tools/Fs/NameToDateTool.php:5)
- [ ] show deleted file size (src/Tools/Fs/Duplicated/Interactive.php:86)
- [ ] colorize duplications (src/Tools/Fs/Duplicated/NoInteractive.php:40)

### DuplicatedFiles
- [ ] progress bar, skip dir, create link after delete original file, inverse selection, show hash
- [ ] first check by file size
- [ ] multithread (calculate hashes in separate threads, compare in separate (full list and slitted list)
- [ ] interactive delete list after comparision process
- [ ] set file in array with size, if file with the same size is detected, then calculate hash of that files and check hash
- [ ] set file path & size in array, size as index, if index exists calculate hashes and add files into array
- [ ] in second iteration check hashes and skip single files

### IfExists
- [ ] finish implementation

### Removal
- [ ] finish implementation

## Git

### Version
- [ ] add version into `composer.json` if not exists (src/Tools/Git/VersionTool.php:140)
- [ ] resolve problem with php-shellcommand lib (set command don't work for more commands)

### Deploy
- [ ] finish implementation

## Info

### Info
- [ ] finish implementation

## Math

### Percent
- [ ] finish implementation (or make facade to https://github.com/chajr/percent)

## System

### History
- [ ] limit (head, tail)
- [ ] part (commands 10-100)
- [ ] time format
- [ ] time period
- [ ] add try/catch for each iteration, display error at end of history
- [ ] unique + sort

### System
- [ ] finish implementation

## Utils

### Opera Backupp
- [ ] finish implementation

### Worker
- [ ] finish implementation