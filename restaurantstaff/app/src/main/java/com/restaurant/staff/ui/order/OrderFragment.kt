package com.restaurant.staff.ui.order

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.lifecycle.lifecycleScope
import androidx.navigation.fragment.findNavController
import androidx.recyclerview.widget.LinearLayoutManager
import com.restaurant.staff.R
import com.restaurant.staff.databinding.FragmentOrderBinding
import com.restaurant.staff.ui.main.MainActivity
import com.restaurant.staff.RestaurantStaffApplication
import com.restaurant.staff.data.model.*
import kotlinx.coroutines.launch

class OrderFragment : Fragment() {

    private var _binding: FragmentOrderBinding? = null
    private val binding get() = _binding!!

    private val viewModel: OrderViewModelSimplified by viewModels {
        OrderViewModelFactory(
            (requireActivity().application as RestaurantStaffApplication).menuRepository,
            (requireActivity().application as RestaurantStaffApplication).orderRepository
        )
    }
    private lateinit var existingOrderItemsAdapter: ExistingOrderItemsAdapter
    private lateinit var searchMenuAdapter: SearchMenuAdapter
    private lateinit var pendingAdapter: ExistingOrderItemsAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentOrderBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        setupUI()
        setupObservers()
        loadData()
    }

    private fun setupUI() {
        // Setup toolbar
        (activity as? MainActivity)?.supportActionBar?.apply {
            title = "Đặt món"
            setDisplayHomeAsUpEnabled(true)
        }

        // Setup existing order items RecyclerView
        existingOrderItemsAdapter = ExistingOrderItemsAdapter(
            onQuantityChanged = { orderItem, quantity ->
                viewModel.updateOrderItemQuantity(orderItem, quantity)
            },
            onRemoveItem = { orderItem ->
                showRemoveItemDialog(orderItem)
            }
        )
        binding.recyclerViewExistingOrders.apply {
            layoutManager = LinearLayoutManager(context)
            adapter = existingOrderItemsAdapter
            android.util.Log.d("OrderFragment", "RecyclerView setup completed")

            // Add layout listener to debug
            addOnLayoutChangeListener { _, _, _, _, _, _, _, _, _ ->
                android.util.Log.d("OrderFragment", "RecyclerView layout changed: width=$width, height=$height")
            }
        }

        // Pending items RecyclerView (read-only)
        pendingAdapter = ExistingOrderItemsAdapter(
            onQuantityChanged = { _, _ -> },
            onRemoveItem = { _ -> }
        )
        binding.recyclerViewPending.apply {
            layoutManager = LinearLayoutManager(context)
            adapter = pendingAdapter
        }

        // Setup search menu RecyclerView
        searchMenuAdapter = SearchMenuAdapter { menuItem ->
            showQuantityDialog(menuItem)
        }
        binding.recyclerViewSearchMenu.apply {
            layoutManager = LinearLayoutManager(context)
            adapter = searchMenuAdapter
        }

        // Setup search functionality
        binding.editTextSearch.addTextChangedListener(object : TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) {}
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {}
            override fun afterTextChanged(s: Editable?) {
                viewModel.searchMenuItems(s?.toString() ?: "")
            }
        })

        // Setup buttons
        binding.buttonSubmitOrder.setOnClickListener {
            viewModel.submitOrder()
        }

        binding.buttonAddMoreItems.setOnClickListener {
            toggleSearchSection()
        }
    }

    private fun setupObservers() {
        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.existingOrderItems.collect { orderItems ->
                android.util.Log.d("OrderFragment", "Received ${orderItems.size} existing order items")
                orderItems.forEach { item ->
                    android.util.Log.d("OrderFragment", "Order item: id=${item.id}, name='${item.itemName}', productId=${item.productId}")
                }
                existingOrderItemsAdapter.submitList(orderItems)
                updateExistingOrdersUI(orderItems)
            }
        }

        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.currentOrder.collect { order ->
                updateOrderInfo(order)
            }
        }

        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.filteredMenuItems.collect { menuItems ->
                android.util.Log.d("OrderFragment", "Received ${menuItems.size} filtered menu items")
                searchMenuAdapter.submitList(menuItems)
                updateSearchMenuUI(menuItems)
            }
        }

        // Observe pending items
        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.pendingItems.collect { pending ->
                // Map PendingItem to a lightweight shown item (unit price unknown until approved)
                val shown = pending.mapIndexed { idx, p ->
                    OrderItem(
                        id = p.id ?: -1000 - idx,
                        orderId = viewModel.currentOrder.value?.id ?: 0,
                        productId = p.productId,
                        quantity = p.quantity,
                        unitPrice = 0.0,
                        totalPrice = 0.0,
                        notes = p.specialInstructions,
                        itemName = p.itemName,
                        specialInstructions = p.specialInstructions,
                        status = "pending",
                        createdAt = "",
                        updatedAt = ""
                    )
                }
                pendingAdapter.submitList(shown)
                binding.groupPending.visibility = if (shown.isEmpty()) View.GONE else View.VISIBLE
            }
        }

        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.isLoadingOrders.collect { isLoading ->
                binding.progressBarOrders.visibility = if (isLoading) View.VISIBLE else View.GONE
            }
        }

        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.isLoadingMenu.collect { isLoading ->
                binding.progressBarMenu.visibility = if (isLoading) View.VISIBLE else View.GONE
            }
        }

        // Observe UI state for feedback
        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.uiState.collect { state ->
                when (state) {
                    is Resource.Loading -> {
                        // Show loading if needed
                        android.util.Log.d("OrderFragment", "UI State: Loading")
                    }
                    is Resource.Success -> {
                        android.util.Log.d("OrderFragment", "UI State: Success")
                        // Show success feedback - only if we have order items (meaning something was added)
                        if (viewModel.existingOrderItems.value.isNotEmpty()) {
                            com.google.android.material.snackbar.Snackbar.make(
                                binding.root,
                                "Đã thêm món thành công!",
                                com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                            ).show()
                        }
                    }
                    is Resource.Error -> {
                        android.util.Log.e("OrderFragment", "UI State: Error - ${state.message}")
                        // Show error feedback
                        com.google.android.material.snackbar.Snackbar.make(
                            binding.root,
                            "Lỗi: ${state.message}",
                            com.google.android.material.snackbar.Snackbar.LENGTH_LONG
                        ).show()
                    }
                    null -> {
                        // Initial state, do nothing
                    }
                }
            }
        }

        viewLifecycleOwner.lifecycleScope.launch {
            viewModel.orderState.collect { state ->
                when (state) {
                    is Resource.Loading -> {
                        binding.progressBar.visibility = View.VISIBLE
                        binding.buttonSubmitOrder.isEnabled = false
                    }
                    is Resource.Success -> {
                        binding.progressBar.visibility = View.GONE
                        binding.buttonSubmitOrder.isEnabled = true
                        // Show success message
                        com.google.android.material.snackbar.Snackbar.make(
                            binding.root,
                            "Thao tác thành công!",
                            com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                        ).show()
                    }
                    is Resource.Error -> {
                        binding.progressBar.visibility = View.GONE
                        binding.buttonSubmitOrder.isEnabled = true
                        // Show error message
                        com.google.android.material.snackbar.Snackbar.make(
                            binding.root,
                            "Lỗi: ${state.message}",
                            com.google.android.material.snackbar.Snackbar.LENGTH_LONG
                        ).show()
                    }
                    else -> {
                        binding.progressBar.visibility = View.GONE
                        binding.buttonSubmitOrder.isEnabled = true
                    }
                }
            }
        }
    }

    private fun updateExistingOrdersUI(orderItems: List<OrderItem>) {
        android.util.Log.d("OrderFragment", "updateExistingOrdersUI called with ${orderItems.size} items")

        if (orderItems.isEmpty()) {
            android.util.Log.d("OrderFragment", "No items, showing empty layout")
            binding.layoutEmptyOrders.visibility = View.VISIBLE
            binding.recyclerViewExistingOrders.visibility = View.GONE
            binding.buttonSubmitOrder.isEnabled = false
        } else {
            android.util.Log.d("OrderFragment", "Has items, showing RecyclerView")
            binding.layoutEmptyOrders.visibility = View.GONE
            binding.recyclerViewExistingOrders.visibility = View.VISIBLE
            binding.buttonSubmitOrder.isEnabled = true

            // Debug: Check RecyclerView dimensions
            binding.recyclerViewExistingOrders.post {
                android.util.Log.d("OrderFragment", "RecyclerView dimensions: width=${binding.recyclerViewExistingOrders.width}, height=${binding.recyclerViewExistingOrders.height}")
                android.util.Log.d("OrderFragment", "RecyclerView visibility: ${binding.recyclerViewExistingOrders.visibility}")
                android.util.Log.d("OrderFragment", "RecyclerView isShown: ${binding.recyclerViewExistingOrders.isShown}")
            }
        }

        // Update total
        val total = viewModel.calculateOrderTotal()
        binding.textTotalAmount.text = "₫${String.format("%,.0f", total)}"
        android.util.Log.d("OrderFragment", "Updated total: $total")
    }

    private fun updateOrderInfo(order: Order?) {
        if (order != null) {
            // Show order ID if orderNumber is null
            val orderIdentifier = order.orderNumber ?: order.id?.toString() ?: "Tạm thời"
            binding.textOrderInfo.text = "Đơn hàng #$orderIdentifier"
            binding.textOrderStatus.text = "Trạng thái: ${getStatusText(order.status)}"
        } else {
            binding.textOrderInfo.text = "Chưa có đơn hàng"
            binding.textOrderStatus.text = ""
        }
    }

    private fun updateSearchMenuUI(menuItems: List<MenuItem>) {
        android.util.Log.d("OrderFragment", "updateSearchMenuUI called with ${menuItems.size} items")
        if (menuItems.isEmpty()) {
            binding.textSearchResult.text = "Không tìm thấy món ăn"
        } else {
            binding.textSearchResult.text = "Tìm thấy ${menuItems.size} món"
        }
    }

    private fun toggleSearchSection() {
        if (binding.layoutSearchSection.visibility == View.VISIBLE) {
            binding.layoutSearchSection.visibility = View.GONE
            binding.buttonAddMoreItems.text = "➕ Thêm món"
        } else {
            binding.layoutSearchSection.visibility = View.VISIBLE
            binding.buttonAddMoreItems.text = "➖ Ẩn tìm kiếm"
            binding.editTextSearch.requestFocus()
        }
    }

    private fun showQuantityDialog(menuItem: MenuItem) {
        val dialogView = LayoutInflater.from(requireContext()).inflate(R.layout.dialog_quantity_selector, null)
        val quantityEditText = dialogView.findViewById<android.widget.EditText>(R.id.editTextQuantity)

        com.google.android.material.dialog.MaterialAlertDialogBuilder(requireContext())
            .setTitle("Chọn số lượng")
            .setMessage("Nhập số lượng cho ${menuItem.name}")
            .setView(dialogView)
            .setPositiveButton("Thêm") { _, _ ->
                val quantityText = quantityEditText.text.toString()
                val quantity = quantityText.toIntOrNull() ?: 1
                if (quantity > 0) {
                    android.util.Log.d("OrderFragment", "Adding ${menuItem.name} with quantity $quantity")
                    viewModel.addMenuItemToOrder(menuItem, quantity)
                    // Clear search
                    binding.editTextSearch.setText("")

                    // Show immediate feedback
                    com.google.android.material.snackbar.Snackbar.make(
                        binding.root,
                        "Đang thêm ${menuItem.name}...",
                        com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                    ).show()
                } else {
                    com.google.android.material.snackbar.Snackbar.make(
                        binding.root,
                        "Số lượng phải lớn hơn 0",
                        com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                    ).show()
                }
            }
            .setNegativeButton("Hủy", null)
            .show()
    }

    private fun showRemoveItemDialog(orderItem: OrderItem) {
        com.google.android.material.dialog.MaterialAlertDialogBuilder(requireContext())
            .setTitle("Xóa món")
            .setMessage("Bạn có chắc muốn xóa ${orderItem.itemName} khỏi đơn hàng?")
            .setPositiveButton("Xóa") { _, _ ->
                viewModel.removeOrderItem(orderItem)
            }
            .setNegativeButton("Hủy", null)
            .show()
    }

    private fun getStatusText(status: String?): String {
        return when (status) {
            "draft" -> "Nháp"
            "submitted" -> "Đã gửi"
            "confirmed" -> "Đã xác nhận"
            "preparing" -> "Đang chế biến"
            "served" -> "Đã phục vụ"
            "paid" -> "Đã thanh toán"
            "cancelled" -> "Đã hủy"
            "pending" -> "Chờ xử lý"
            else -> status ?: "Đang tạo"
        }
    }

    private fun loadData() {
        // Get table ID from arguments
        val tableId = arguments?.getInt("tableId") ?: return
        viewModel.loadTableData(tableId)
        viewModel.loadMenuItems() // Load menu items for search
    }

    override fun onResume() {
        super.onResume()
        // Refresh data when fragment is resumed (e.g., when returning from another screen)
        val tableId = arguments?.getInt("tableId") ?: return
        viewModel.refreshTableData(tableId)
        viewModel.loadMenuItems() // Reload menu items
    }

    override fun onPause() {
        super.onPause()
        // Stop polling when fragment is paused
        viewModel.stopPolling()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        viewModel.stopPolling()
        _binding = null
    }
}
