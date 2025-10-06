package com.restaurant.staff.ui.tables

import android.text.SpannableString
import android.text.style.BackgroundColorSpan
import android.text.style.ForegroundColorSpan
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.core.content.ContextCompat
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.restaurant.staff.R
import com.restaurant.staff.data.model.Table
import com.restaurant.staff.utils.hasActiveOrder
import com.restaurant.staff.utils.displayOrderTime
import com.restaurant.staff.utils.displayTotalAmount
import com.restaurant.staff.databinding.ItemTableBinding

class TablesAdapter(
    private val onTableClick: (Table) -> Unit
) : ListAdapter<Table, TablesAdapter.TableViewHolder>(TableDiffCallback()) {

    private var searchQuery: String = ""
    private var lastVisitedTableId: Int? = null

    fun setSearchQuery(query: String) {
        this.searchQuery = query
        notifyDataSetChanged()
    }

    fun setLastVisitedTable(tableId: Int?) {
        this.lastVisitedTableId = tableId
        notifyDataSetChanged()
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): TableViewHolder {
        val binding = ItemTableBinding.inflate(LayoutInflater.from(parent.context), parent, false)
        return TableViewHolder(binding)
    }

    override fun onBindViewHolder(holder: TableViewHolder, position: Int) {
        val table = getItem(position)
        holder.bind(table, searchQuery, lastVisitedTableId)
    }

    inner class TableViewHolder(
        private val binding: ItemTableBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        init {
            binding.root.setOnClickListener {
                val position = adapterPosition
                if (position != RecyclerView.NO_POSITION) {
                    onTableClick(getItem(position))
                }
            }
        }

        fun bind(table: Table, searchQuery: String, lastVisitedTableId: Int?) {
            val context = itemView.context

            // Debug: Log all table information
            android.util.Log.d("TablesAdapter", "Table ${table.name} - Full Info:")
            android.util.Log.d("TablesAdapter", "  - ID: ${table.id}")
            android.util.Log.d("TablesAdapter", "  - Name: ${table.name}")
            android.util.Log.d("TablesAdapter", "  - Area ID: ${table.areaId}")
            android.util.Log.d("TablesAdapter", "  - Capacity: ${table.capacity}")
            android.util.Log.d("TablesAdapter", "  - Status: ${table.status}")
            android.util.Log.d("TablesAdapter", "  - Position: (${table.positionX}, ${table.positionY})")
            android.util.Log.d("TablesAdapter", "  - Area Name: ${table.areaName}")
            android.util.Log.d("TablesAdapter", "  - Current Order ID: ${table.currentOrderId}")
            android.util.Log.d("TablesAdapter", "  - Order Number: ${table.orderNumber}")
            android.util.Log.d("TablesAdapter", "  - Order Status: ${table.orderStatus}")
            android.util.Log.d("TablesAdapter", "  - Customer Count: ${table.customerCount}")
            android.util.Log.d("TablesAdapter", "  - Total Amount: ${table.totalAmount}")
            android.util.Log.d("TablesAdapter", "  - Order Created At: ${table.orderCreatedAt}")
            android.util.Log.d("TablesAdapter", "  - Waiter Name: ${table.waiterName}")
            android.util.Log.d("TablesAdapter", "  - Pending Amount: ${table.pendingAmount}")
            android.util.Log.d("TablesAdapter", "  - Active Orders: ${table.activeOrders}")
            android.util.Log.d("TablesAdapter", "  - Has Active Order: ${table.hasActiveOrder}")

            // Set table number with highlighting
            binding.textTableNumber.text = highlightText("Bàn ${table.name}", searchQuery)

            // Set table name with highlighting (show capacity info)
            val tableInfo = "Sức chứa: ${table.capacity} người"
            binding.textTableName.text = highlightText(tableInfo, searchQuery)

            // Set area name with highlighting
            binding.textAreaName.text = highlightText(table.areaName ?: "", searchQuery)

            // Set status
            binding.textStatus.text = getStatusText(table.status)
            binding.textStatus.setBackgroundResource(getStatusBackground(table.status))

            // Only show order info when there is a pending amount to pay
            if (table.pendingAmount != null && table.pendingAmount!! > 0) {
                binding.layoutOrderInfo.visibility = View.VISIBLE

                // Show order time if available
                if (table.orderCreatedAt != null) {
                    binding.textOrderTime.text = "Đặt lúc: ${table.displayOrderTime}"
                } else {
                    binding.textOrderTime.text = "Có đơn hàng đang xử lý"
                }

                // Show pending amount (table-level total)
                binding.textTotalAmount.text = "Tổng: ${table.displayTotalAmount}"
            } else {
                binding.layoutOrderInfo.visibility = View.GONE
            }

            // Highlight last visited table
            val isLastVisited = lastVisitedTableId == table.id
            if (isLastVisited) {
                binding.root.setBackgroundResource(R.drawable.table_highlight_background)
            } else {
                binding.root.setBackgroundResource(R.drawable.table_item_background)
            }
        }

        private fun highlightText(text: String, query: String): SpannableString {
            val spannableString = SpannableString(text)

            if (query.isNotEmpty() && text.contains(query, ignoreCase = true)) {
                val startIndex = text.indexOf(query, ignoreCase = true)
                val endIndex = startIndex + query.length

                spannableString.setSpan(
                    BackgroundColorSpan(ContextCompat.getColor(itemView.context, R.color.search_highlight)),
                    startIndex,
                    endIndex,
                    SpannableString.SPAN_EXCLUSIVE_EXCLUSIVE
                )

                spannableString.setSpan(
                    ForegroundColorSpan(ContextCompat.getColor(itemView.context, R.color.search_highlight_text)),
                    startIndex,
                    endIndex,
                    SpannableString.SPAN_EXCLUSIVE_EXCLUSIVE
                )
            }

            return spannableString
        }

        private fun getStatusText(status: String?): String {
            return when (status?.lowercase()) {
                "available" -> "Trống"
                "occupied" -> "Có khách"
                "reserved" -> "Đã đặt"
                "maintenance" -> "Bảo trì"
                else -> "Không xác định"
            }
        }

        private fun getStatusBackground(status: String?): Int {
            return when (status?.lowercase()) {
                "available" -> R.drawable.status_available_background
                "occupied" -> R.drawable.status_occupied_background
                "reserved" -> R.drawable.status_reserved_background
                "maintenance" -> R.drawable.status_maintenance_background
                else -> R.drawable.status_available_background
            }
        }
    }

    private class TableDiffCallback : DiffUtil.ItemCallback<Table>() {
        override fun areItemsTheSame(oldItem: Table, newItem: Table): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: Table, newItem: Table): Boolean {
            return oldItem == newItem
        }
    }
}
