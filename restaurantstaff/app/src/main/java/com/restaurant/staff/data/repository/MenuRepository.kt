package com.restaurant.staff.data.repository

import com.restaurant.staff.data.local.MenuDao
import com.restaurant.staff.data.model.*
import com.restaurant.staff.data.remote.MenuApiService
import com.restaurant.staff.utils.NetworkUtils
import com.restaurant.staff.data.model.NetworkResult
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow

class MenuRepository(
    private val menuApiService: MenuApiService,
    private val menuDao: MenuDao
) {

    fun getAllCategoriesFlow(): Flow<List<Category>> {
        return menuDao.getAllCategoriesFlow()
    }

    fun getMenuItemsByCategoryFlow(categoryId: Int): Flow<List<MenuItem>> {
        return menuDao.getMenuItemsByCategoryFlow(categoryId)
    }

    fun getAllMenuItemsFlow(): Flow<List<MenuItem>> {
        return menuDao.getAllMenuItemsFlow()
    }

    suspend fun getAllMenuItems(): NetworkResult<PaginatedResponse<MenuItem>> {
        return try {
            val result = NetworkUtils.safeApiCall {
                menuApiService.getAllMenuItems(1, 100, true)
            }

            when (result) {
                is NetworkResult.Success<PaginatedResponse<MenuItem>> -> {
                    val paginatedResponse = result.data
                    // Cache items
                    menuDao.insertMenuItems(paginatedResponse.items)
                    result
                }
                is NetworkResult.Error<PaginatedResponse<MenuItem>> -> {
                    // Return cached data if available
                    val cachedItems = menuDao.getAllMenuItems()
                    if (cachedItems.isNotEmpty()) {
                        val cachedResponse = PaginatedResponse(
                            items = cachedItems,
                            pagination = Pagination(1, cachedItems.size, cachedItems.size, 1)
                        )
                        NetworkResult.Success(cachedResponse)
                    } else {
                        result
                    }
                }
                is NetworkResult.Loading<PaginatedResponse<MenuItem>> -> NetworkResult.Loading()
            }
        } catch (e: Exception) {
            // Return cached data if available
            val cachedItems = menuDao.getAllMenuItems()
            if (cachedItems.isNotEmpty()) {
                val cachedResponse = PaginatedResponse(
                    items = cachedItems,
                    pagination = Pagination(1, cachedItems.size, cachedItems.size, 1)
                )
                NetworkResult.Success(cachedResponse)
            } else {
                NetworkResult.Error(e.message ?: "Failed to get menu items")
            }
        }
    }

    suspend fun refreshCategories(): Flow<Resource<List<Category>>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { menuApiService.getCategories() }

            when (result) {
                is NetworkResult.Success<List<Category>> -> {
                    val categories = result.data
                    menuDao.insertCategories(categories)
                    emit(Resource.Success(categories))
                }
                is NetworkResult.Error<List<Category>> -> {
                    // Return cached data if available
                    val cachedCategories = menuDao.getAllCategories()
                    if (cachedCategories.isNotEmpty()) {
                        emit(Resource.Success(cachedCategories))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading<List<Category>> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Return cached data if available
            val cachedCategories = menuDao.getAllCategories()
            if (cachedCategories.isNotEmpty()) {
                emit(Resource.Success(cachedCategories))
            } else {
                emit(Resource.Error(e.message ?: "Failed to refresh categories"))
            }
        }
    }

    suspend fun refreshMenuItemsByCategory(categoryId: Int): Flow<Resource<CategoryWithItems>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall {
                menuApiService.getItemsByCategory(categoryId)
            }

            when (result) {
                is NetworkResult.Success<CategoryWithItems> -> {
                    val categoryWithItems = result.data

                    // Cache the category and items
                    menuDao.insertCategory(categoryWithItems.category)
                    menuDao.insertMenuItems(categoryWithItems.items)

                    emit(Resource.Success(categoryWithItems))
                }
                is NetworkResult.Error<CategoryWithItems> -> {
                    // Return cached data if available
                    val cachedCategory = menuDao.getCategoryById(categoryId)
                    val cachedItems = menuDao.getMenuItemsByCategory(categoryId)

                    if (cachedCategory != null) {
                        val cachedResult = CategoryWithItems(cachedCategory, cachedItems)
                        emit(Resource.Success(cachedResult))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading<CategoryWithItems> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Return cached data if available
            val cachedCategory = menuDao.getCategoryById(categoryId)
            val cachedItems = menuDao.getMenuItemsByCategory(categoryId)

            if (cachedCategory != null) {
                val cachedResult = CategoryWithItems(cachedCategory, cachedItems)
                emit(Resource.Success(cachedResult))
            } else {
                emit(Resource.Error(e.message ?: "Failed to refresh menu items"))
            }
        }
    }

    suspend fun refreshAllMenuItems(page: Int = 1, limit: Int = 100): Flow<Resource<PaginatedResponse<MenuItem>>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall {
                menuApiService.getAllMenuItems(page, limit, true)
            }

            when (result) {
                is NetworkResult.Success<PaginatedResponse<MenuItem>> -> {
                    val paginatedResponse = result.data

                    // Cache all items
                    if (page == 1) {
                        // Clear existing data only on first page
                        menuDao.deleteAllMenuItems()
                    }
                    menuDao.insertMenuItems(paginatedResponse.items)

                    emit(Resource.Success(paginatedResponse))
                }
                is NetworkResult.Error<PaginatedResponse<MenuItem>> -> {
                    // Return cached data if available
                    val cachedItems = menuDao.getAllMenuItems()
                    if (cachedItems.isNotEmpty()) {
                        val cachedResponse = PaginatedResponse(
                            items = cachedItems,
                            pagination = Pagination(1, cachedItems.size, cachedItems.size, 1)
                        )
                        emit(Resource.Success(cachedResponse))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading<PaginatedResponse<MenuItem>> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Return cached data if available
            val cachedItems = menuDao.getAllMenuItems()
            if (cachedItems.isNotEmpty()) {
                val cachedResponse = PaginatedResponse(
                    items = cachedItems,
                    pagination = Pagination(1, cachedItems.size, cachedItems.size, 1)
                )
                emit(Resource.Success(cachedResponse))
            } else {
                emit(Resource.Error(e.message ?: "Failed to refresh menu items"))
            }
        }
    }

    suspend fun searchMenuItems(
        query: String,
        categoryId: Int? = null
    ): Flow<Resource<MenuSearchResult>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall {
                menuApiService.searchMenuItems(query, categoryId, true)
            }

            when (result) {
                is NetworkResult.Success<MenuSearchResult> -> {
                    emit(Resource.Success(result.data))
                }
                is NetworkResult.Error<MenuSearchResult> -> {
                    // Fallback to local search
                    val localResults = if (categoryId != null) {
                        menuDao.searchMenuItemsByCategory(categoryId, "%$query%")
                    } else {
                        menuDao.searchMenuItems("%$query%")
                    }

                    val searchResult = MenuSearchResult(
                        query = query,
                        categoryId = categoryId,
                        results = localResults,
                        count = localResults.size
                    )
                    emit(Resource.Success(searchResult))
                }
                is NetworkResult.Loading<MenuSearchResult> -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Fallback to local search
            val localResults = if (categoryId != null) {
                menuDao.searchMenuItemsByCategory(categoryId, "%$query%")
            } else {
                menuDao.searchMenuItems("%$query%")
            }

            val searchResult = MenuSearchResult(
                query = query,
                categoryId = categoryId,
                results = localResults,
                count = localResults.size
            )
            emit(Resource.Success(searchResult))
        }
    }

    // Local operations
    suspend fun getMenuItemById(itemId: Int): MenuItem? {
        return menuDao.getMenuItemById(itemId)
    }

    suspend fun getPopularItems(): List<MenuItem> {
        return menuDao.getPopularItems()
    }

    suspend fun getCategoryById(categoryId: Int): Category? {
        return menuDao.getCategoryById(categoryId)
    }

    // Cache management
    suspend fun clearMenuCache() {
        menuDao.deleteAllCategories()
        menuDao.deleteAllMenuItems()
    }
}

