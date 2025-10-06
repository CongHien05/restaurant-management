package com.restaurant.staff.data.local

import androidx.room.*
import com.restaurant.staff.data.model.Category
import com.restaurant.staff.data.model.MenuItem
import kotlinx.coroutines.flow.Flow

@Dao
interface MenuDao {

    // Categories
    @Query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")
    fun getAllCategoriesFlow(): Flow<List<Category>>

    @Query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC")
    suspend fun getAllCategories(): List<Category>

    @Query("SELECT * FROM categories WHERE id = :categoryId")
    suspend fun getCategoryById(categoryId: Int): Category?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertCategories(categories: List<Category>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertCategory(category: Category)

    @Update
    suspend fun updateCategory(category: Category)

    @Delete
    suspend fun deleteCategory(category: Category)

    @Query("DELETE FROM categories")
    suspend fun deleteAllCategories()

    // Menu Items (Products)
    @Query("SELECT * FROM products WHERE category_id = :categoryId AND status = 'active' ORDER BY sort_order ASC")
    fun getMenuItemsByCategoryFlow(categoryId: Int): Flow<List<MenuItem>>

    @Query("SELECT * FROM products WHERE category_id = :categoryId AND status = 'active' ORDER BY sort_order ASC")
    suspend fun getMenuItemsByCategory(categoryId: Int): List<MenuItem>

    @Query("SELECT * FROM products WHERE status = 'active' ORDER BY category_id ASC, sort_order ASC")
    fun getAllMenuItemsFlow(): Flow<List<MenuItem>>

    @Query("SELECT * FROM products WHERE status = 'active' ORDER BY category_id ASC, sort_order ASC")
    suspend fun getAllMenuItems(): List<MenuItem>

    @Query("SELECT * FROM products WHERE id = :itemId")
    suspend fun getMenuItemById(itemId: Int): MenuItem?

    @Query("SELECT * FROM products WHERE status = 'active' ORDER BY sort_order ASC LIMIT 10")
    suspend fun getPopularItems(): List<MenuItem>

    @Query("SELECT * FROM products WHERE (name LIKE :query OR description LIKE :query) AND status = 'active' ORDER BY name ASC")
    suspend fun searchMenuItems(query: String): List<MenuItem>

    @Query("SELECT * FROM products WHERE category_id = :categoryId AND (name LIKE :query OR description LIKE :query) AND status = 'active' ORDER BY name ASC")
    suspend fun searchMenuItemsByCategory(categoryId: Int, query: String): List<MenuItem>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertMenuItems(items: List<MenuItem>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertMenuItem(item: MenuItem)

    @Update
    suspend fun updateMenuItem(item: MenuItem)

    @Delete
    suspend fun deleteMenuItem(item: MenuItem)

    @Query("DELETE FROM products")
    suspend fun deleteAllMenuItems()
}

