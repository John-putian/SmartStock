<?php
/** Product category maintenance (Administrator / Manager only). */
class CategoryController
{
    public static function index(): void
    {
        view('categories/index', ['categories' => Category::all()], 'Product Categories');
    }

    public static function save(): void
    {
        verify_csrf();
        $id   = (int) input('id', 0);
        $name = trim((string) input('name', ''));
        $desc = trim((string) input('description', ''));

        if ($name === '') {
            flash('warning', 'A category name is required.');
            redirect('categories');
        }
        $dupe = Database::value(
            'SELECT COUNT(*) FROM categories WHERE name = ?' . ($id ? ' AND id <> ?' : ''),
            $id ? [$name, $id] : [$name]);
        if ((int) $dupe > 0) {
            flash('warning', 'A category with that name already exists.');
            redirect('categories');
        }

        if ($id) {
            Category::update($id, $name, $desc);
            flash('success', 'Category updated.');
        } else {
            Category::create($name, $desc);
            flash('success', 'Category <strong>' . e($name) . '</strong> has been added.');
        }
        redirect('categories');
    }

    public static function delete(): void
    {
        $id = (int) input('id');
        if (Category::delete($id)) {
            flash('success', 'Category deleted.');
        } else {
            flash('warning', 'That category still has items assigned to it and cannot be deleted.');
        }
        redirect('categories');
    }
}
