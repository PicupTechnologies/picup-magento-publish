from zipfile import ZipFile, ZIP_DEFLATED, is_zipfile
from sys import argv
import os


# Format parameters

args = {}

for i in range(1, len(argv)):
    key, val = argv[i].split('=')
    args[key] = val


def make_zip(zip_obj, cur_dir, ignored_files=[]):

    in_dir = None
    if cur_dir == '':
        in_dir = os.listdir()
    else:
        in_dir = os.listdir(cur_dir)

    for file in in_dir:
        file_dir = cur_dir + file

        if file in ignored_files:
            continue

        if file[0] == '.':
            continue

        print('Writing', file_dir, 'to', zip_obj.filename)
        if os.path.isdir(file_dir):
            zip_obj.write(file_dir, compress_type=ZIP_DEFLATED)
            make_zip(zip_obj, file_dir + '/', ignored_files=ignored_files)
        else:
            if file_dir.split('.')[-1] == 'zip':
                continue

            zip_obj.write(file_dir, compress_type=ZIP_DEFLATED)

directory = ''
if 'dir' in args:
    directory = args['dir']
print(directory)

zip_name = 'BACKUP.zip'
if 'zip' in args:
    zip_name = args['zip'] + '.zip'
zip_file = ZipFile(zip_name, 'w')
print(zip_name)

ignore = []
if 'ignore' in args:
    ignore = args['ignore'].split(',')
ignore.append(argv[0])
print(ignore)

make_zip(zip_file, directory, ignored_files=ignore)

zip_file.close()
print('Completed compressing 100% of files.')
